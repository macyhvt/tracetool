<?php
/* define application namespace */
namespace Nematrack\Service;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use Monolog\Logger;
use Nematrack\App;
use Nematrack\Connectivity\Machine;
use Nematrack\Factory;
use Nematrack\Utility\Filter\InputFilter;
use Nematrack\Messager;
use Nematrack\Model;
use Nematrack\Service;
use Nematrack\Text;
use Throwable;
use function is_a;

/**
 * Class description
 *
 * @see {@link https://kinsta.com/blog/http-status-codes/}
 *
 *@todo - implement access control (e.g. via API key)
 *@todo - catch hammering (code 429: "Too many requests") and ban hammering clients
 */
class Api extends Service
{
	// Task map to map public function names to internal (real function names).
	private static array $taskMap = [
		// Model "Article"
		'upload'     => 'xhrFileUpload',
		// Model "Error"
		'tracked'    => 'isTracked',	// ADDED on 2023-05-25
		// Model "Parts"
		'book'       => 'book',
		// Model "Part"
		'artnum'     => 'getArticleNumber',	// changed URL var to   "artnum"   from "getartnum"
		'labeldata'  => 'getLabelData',
		'presslog'   => 'handlePressinData',
		// Model "Process"
		'approve'    => 'approve',
		// Model "Statistics"
		'monitor'    => 'getOutputMonitorData',
		// Model "User"
//		'auth'       => 'authorize'
	];

	/**
	 * @var    Logger
	 * @since  2.8.0
	 */
	protected Logger $logger;

	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   0.1
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		// Create logger instance providing channel name (like a namespace).
		$this->logger = Factory::getLogger([
			'context' => 'API',
			'type'    => 'rotate',
			'path'    => FTKPATH_LOGS . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'api.log',
			'level'   => 'INFO'
		]);
	}

	/**
	 * Enter description...
	 *
	 * @param   string $modelFunction
	 * @param   array  $args  Optional. Array of arguments to be passed to the object function.
	 *
	 * @return  mixed   Return value depends on object and function to execute.
	 *
	 * @since   2.8
	 */
	public function call(string $modelFunction, ...$args)
	{
		[$model, $task] = explode('.', $modelFunction);

		$input    = App::getInput();
		$filter   = new InputFilter;
		$referer  = new Uri($input->server->getUrl('HTTP_REFERER'));	// FIXME - validate the URL is internal as a matter of security !!!

		// Get additional function args.
		$xtraArgs = (func_num_args() > 1 ? func_get_arg(1) : []);
		// Put into a Registry object for easier data access.
		$xtraArgs = new Registry($xtraArgs);

		// If there's an article id pick it for the query specification.
		$response = null;
		$format   = '' . trim($xtraArgs->get('format', ''));

		try
		{
			$model = Model::getInstance($model, ['language' => $input->getWord('hl', '')]);

			if (!is_a($model, 'Nematrack\Model'))
			{
				http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

				// TODO - translate
				$response = ['error' => Text::translate('Data provider not available.')];

				if ($format === 'json')
				{
					header("Content-type: application/json; charset=utf-8");
				}
			}
			else
			{
				// Identify the real implemented model function from the 'task <--> function' map.
				$realfunc = ArrayHelper::getValue(static::$taskMap, $task);

				/*// @debug
				header("Content-type: application/json; charset=utf-8");
				echo json_encode([
					'format'        => $format,
					'model'         => $model,
					'task'          => $task,
					'modelFunction' => $modelFunction,
					'realfunc'      => $realFunction,
					'GET'           => $input->get->getArray(),
					'POST'          => $input->post->getArray(),
					'payload'       => $input->post->getArray(),
					'xtraArgs'      => $xtraArgs
				]);
				exit;*/

				// Catch calls for unsupported services.
				if (!method_exists($model, $realfunc))
				{
					http_response_code( http_response_code() ?: '501' );	// means: "Not Implemented"

					// TODO - translate
					$response = ['error' => Text::translate('No such service.')];

					if ($format === 'json')
					{
						header("Content-type: application/json; charset=utf-8");
					}
				}
				else
				{
					switch ($modelFunction)
					{
						case 'article.upload' :
							// Log REQUEST and Response
//							$this->logger->log('info', $modelFunction, $_REQUEST);

							$response = $model->{$realfunc}();

//							$this->logger->log('info', $modelFunction, (array) $response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));

									$response = ['message' => $response];
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}

								header("Content-type: application/json; charset=utf-8");
							}
						break;

						// ADDED on 2023-05-25
						case 'error.tracked' :
							// Log REQUEST and Response
							$this->logger->log('info', $modelFunction, $_REQUEST);

							$response = $model->{$realfunc}( $xtraArgs->get('eid'), '' . trim($xtraArgs->get('enum', '')) );

							$this->logger->log('info', $modelFunction, (array) $response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								header("Content-type: application/json; charset=utf-8");

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}
							}
						break;

						case 'parts.book' :
							// Log REQUEST and Response
							$this->logger->log('info', $modelFunction, $_REQUEST);

							$response = $model->{$task}(
								intval($xtraArgs->get('aid')),
								'' . trim($xtraArgs->get('quality')),
								intval($xtraArgs->get('pid')),
								$xtraArgs->toArray()
							);

							$this->logger->log('info', $modelFunction, (array) $response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));

									$response = ['message' => $response];
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}
							}
						break;

						case 'part.artnum' :
							// Log REQUEST and Response
							$this->logger->log('info', $modelFunction, $_REQUEST);

							$response = $model->{$realfunc}( '' . trim($xtraArgs->get('tc', '')) );

							$this->logger->log('info', $modelFunction, (array) $response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								header("Content-type: application/json; charset=utf-8");

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}
							}
						break;

						case 'part.labeldata' :
							// Log REQUEST and Response
							$this->logger->log('info', $modelFunction, $_REQUEST);

							$response = $model->{$realfunc}( '' . trim($xtraArgs->get('tc', '')) );

							$this->logger->log('info', $modelFunction, (array) $response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								header("Content-type: application/json; charset=utf-8");

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}
							}
						break;

						case 'part.presslog' :
							// Merge $_GET variable 'plant', which is set by the machine via URI query parameter
							$request = array_merge(
								$input->post->getArray(),
								[
									'plant' => $input->request->getString('plant','n/a')
								]
							);

							// Log request response.
//							$this->logger->log('info', $modelFunction, (array) $response);	// DiSABLED on 2023-06-07 because it is unnecessary + dupe

							// Log request
							// See: https://github.com/Seldaek/monolog/blob/HEAD/doc/message-structure.md
							$this->logger->log('info', $modelFunction, $request);

							try
							{
								$response = Machine::getInstance('pressone')->persist($request);

								// Log processing response
								$this->logger->log('info', $modelFunction, (array) $response);
							}
							catch (Throwable $e)
							{
								// FIXME - this is causing session already started error caused in Messager::setMessage();
								/*// Report message.
								Messager::setMessage([
									'type' => 'error',
									'text' => $e->getMessage()
								]);*/

								// Log processing error
								$this->logger->log('error', $modelFunction, ['error' => $e->getMessage(), 'code' => $e->getCode()]);

								// Workaround for JSON communication
								throw new Exception($e->getMessage());
							}

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								header('Content-type: application/json; charset=utf-8');

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}
							}
						break;

						/*case 'statistics.monitor' :
							$monitor  = $input->getCmd('monitor');
							$proNum   = $input->getAlnum('project');
							$pids     = $input->get('pids', [], 'ARRAY');
							$today    = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('d.m.Y');
							$dateFrom = $input->getCmd('dateFrom') ?? $today;
							$dateTo   = $input->getCmd('dateTo')   ?? $today;

							$project   = (isset($proNum)
								? $model->getInstance('project', ['language' => $model->get('language')])->getProjectByNumber($proNum)
								: $model->getInstance('project', ['language' => $model->get('language')])->getItem(0)
							);
							// $processes  = $model->getInstance('processes', ['language' => $model->get('language')])->getList();
							$processes  = $model->getInstance('processes', ['language' => $model->get('language')])->getList();
							$pidAbbrMap = array_combine($pids, array_fill(0, count($pids), null));

							foreach ($pidAbbrMap as $pid => &$value)
							{
								$value = ArrayHelper::getValue((array) ArrayHelper::getValue($processes, $pid), 'abbreviation');
							}

							$response = $model->{$realfunc}($project->get('proID'), $dateFrom, $dateTo, $pidAbbrMap, $pids);
							$response = ArrayHelper::getValue($response, $monitor, $response);

							array_walk($response, function(&$line)
							{
								$line = preg_replace('/(^\||\s+|\|$)/i', '', trim($line));
								$line = str_replace('|', ';', $line);
								$line = (!preg_match('/[a-z0-9]+/i', $line)) ? '' : $line;
							});
							$response = array_filter($response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT'));
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								header('Content-type: application/json; charset=utf-8');

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}
							}
						break;*/

						case 'process.approve' :
							// Tasks to do

							// 1. Approve that email and password are provided.
							// 2. Approve the user exists.
							// 3. Approve the user is a quality responsible.
							// 4. Approve the process is managed by the user's organisation
							// 5. Approve the tracking time window is closed
							// 6. Give a 5 minutes editing time

							$get  = $input->get->getArray();
							$post = $input->post->getArray();

							// Extraction of POST-vars will create $email and $password given they're set.
							extract($post);

							if (isset($password))
							{
								// Mask password to prevent it from being logged.
								$post['password'] = sprintf('%s REMOVED %s', str_repeat('*', 3), str_repeat('*', 3));
							}

							// Log REQUEST and Response
//							$this->logger->log('info', $modelFunction, array_merge($get, $post));

							// Get user model.
							$userModel  = $model->getInstance('user', ['language' => $input->getWord('hl')]);

							// 1. Authenticate the quality manager via its login credentials.
							$approver   = $userModel->getUserByCredentials($email, $password);
							$approverID = $approver->get('userID');

							// 2. Authenticated the worker via its unique id.
							$approved   = $userModel->getItem((int) $uid);	// FIXME - doesn't return a populated user entity
							$permiteeID = $approved->get('userID');			// FIXME - will be empty because of issue in prev. line
							$permiteeID = (int) $uid;						// sets value of $_POST[uid]

							// Init approval result.
							$status     = false;
							$successMsg = [Text::translate('COM_FTK_SYSTEM_MESSAGE_APPROVAL_GRANTED_TEXT', $input->getWord('hl'))];
							$errorMsg   = [Text::translate('COM_FTK_SYSTEM_MESSAGE_APPROVAL_DENIED_TEXT',  $input->getWord('hl'))];

							// @debug
//							echo '<pre>post: ' . print_r($post, true) . '</pre>'; die;

							// Self-approval is not allowed.
							if ($approverID == $permiteeID)
							{
								// @debug
								// echo '<pre>' . print_r(' !!! SELF-Approval attempt !!!', true) . '</pre>';

								// TODO - prevent self-approval
								$errorMsg[] = Text::translate('COM_FTK_SYSTEM_MESSAGE_SELF_APPROVAL_IS_FORBIDDEN_TEXT', $input->getWord('hl'));
							}
							else	// $approverID != $permiteeID
							{
								// @debug
								// echo '<pre>' . print_r('ELSE', true) . '</pre>';

								// Approve only if both the worker's userID + the quality manager's userID are available.
								if ($approverID/* && is_a($approver, 'Nematrack\Entity\User')*/ &&
									$permiteeID/* && is_a($approver, 'Nematrack\Entity\User')*/)
								{
									// @debug
//									echo '<pre>' . print_r('ELSE > IF', true) . '</pre>'; die;

									$data = [
										'partID'      => $input->getInt('ptid'),
										'procID'      => $input->getInt('pid'),
										'permiteeID'  => $permiteeID,	// ID of user who previously tracked that process
										// TODO - identify user via email, password
//										'approverID'  => null,
										'approverID'  => $approverID,	// ID of user who's approving that process
										'IP'          => sprintf("%u", ip2long($input->server->get('REMOTE_ADDR', '0', 'STRING'))),
										'dateISO8601' => (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('c'),
										'token'       => $input->getAlnum('token'),
									];

									$status = $model->{$realfunc}($data);	// return value is expected to be the ID of the inserted row (insert_id)
								}
								else
								{
									// @debug
//									echo '<pre>' . print_r('ELSE > ELSE', true) . '</pre>'; die;

									switch (true)
									{
										case (!$approverID && $permiteeID) :
											$errorMsg[] = sprintf('%s<br>%s',
												Text::translate('COM_FTK_SYSTEM_MESSAGE_QA_AUTHENTICATION_FAILED_TEXT', $input->getWord('hl')),
												Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_ENTER_CORRECT_AUTHENTICATION_DATA_TEXT', $input->getWord('hl'))
											);
										break;

										case ( $approverID && !$permiteeID) :
											$errorMsg[] = sprintf('%s<br>%s',
												Text::translate('COM_FTK_SYSTEM_MESSAGE_WORKER_AUTHENTICATION_FAILED_TEXT', $input->getWord('hl')),
												Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_ENTER_CORRECT_AUTHENTICATION_DATA_TEXT', $input->getWord('hl'))
											);
										break;
									}
								}
							}

							if ($format === 'jsonp')
							{
								$response = [
									'approver' => $approverID,
									'approved' => $permiteeID,
									'status'   => $status,
									'action'   => null,
									'feedback' => null,
								];

								if (true  == $status)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									$successMsg[] = Text::translate('COM_FTK_SYSTEM_MESSAGE_YOU_ARE_NOW_FORWARDED_TEXT', $input->getWord('hl'));

									$referer->delVar('task');
									$referer->setVar('layout',   'edit_new');
									$referer->setVar('approved', 'true');
									$referer->setFragment('p-' . hash('CRC32', $referer->getVar('pid')));

									$response = array_merge($response, [
										'status'   => 'success',
										'action' => [
											'redirect' => true,
											'target'   => $referer->toString(),
											'delay'    => 1000	// waiting time in ms until redirecting
										],
										'feedback' => [
											'message' => [
												'type'    => 'success',
												'text'    => implode(':<br>', $successMsg),
												'display' => true
											]
										]
									]);
								}

								if (false == $status)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									$response = array_merge($response, [
										'status'   => 'error',
										/*'action' => [
											'redirect' => true,
											// return to previous page
											'target'   => $referer->toString()
										],*/
										'feedback' => [
											'message' => [
												'type'    => 'error',
												'text'    => implode(':<br>', $errorMsg),
												'display' => true
											]
										]
									]);
								}

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($status))
								{
									Messager::clearMessageQueue();
								}

								// Log processing result.
//								$this->logger->log('info', $modelFunction, (array) $response);

								header("Content-type: text/javascript; charset=utf-8");
							}

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_EXECUTED_TEXT'));

									$response = ['message' => $response];
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}

								header("Content-type: application/json; charset=utf-8");
							}
						break;

						/*case 'user.auth' :
							// Log REQUEST and Response
							$this->logger->log('info', $modelFunction, $_REQUEST);

							$response = $model->{$realfunc}($input->post->getArray());

							$this->logger->log('info', $modelFunction, (array) $response);

							if ($format === 'json')
							{
								if (true === $response)
								{
									http_response_code( http_response_code() ?: '200' );	// means: "OK"

									// Get system message, if there is any.
									$response = Messager::getMessage();
									// Check if there's a system message or assign a fallback message.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_EXECUTED_TEXT'));

									$response = ['message' => $response];
								}

								if (false === $response)
								{
									http_response_code( http_response_code() ?: '500' );	// means: "Internal server error"

									// Get system error, if there is any.
									$response = Messager::getError();
									// Check if there's a system error or assign a fallback error.
									$response = (!empty($response) ? $response : Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'));
								}

//								header("Content-type: application/json; charset=utf-8");	// DiSABLED on 2023-05-30 because this line is supposed to be unnecessary

								// Clear message queue to prevent message(s) from being rendered on page reload.
								if (!empty($response))
								{
									Messager::clearMessageQueue();
								}

								header("Content-type: application/json; charset=utf-8");
							}
						break;*/
					}
				}
			}
		}
		catch (JsonException $e)
		{
			// Do nothing
		}
		catch (Exception $e)
		{
			// @debug
//			echo '<pre>Exception 2 message: '  . print_r($e->getMessage(), true) . '</pre>';
//			echo '<pre>Exception 2 has code? ' . print_r($e->getCode() ? 'YES' : 'NO', true) . '</pre>';
//			echo '<pre>Exception 2 code: '     . print_r($e->getCode(), true) . '</pre>';
//			echo '<pre>Exception 2 set http_response_code: ' . print_r(($e->getCode() ?: '500'), true) . '</pre>';
//			die;

			/*// DiSABLED on 2023-07-06 - replaced by following switch/case block.
			if ($format === 'json')
			{
				http_response_code( $e->getCode() ?: '500' );

				$response = ['error' => $e->getMessage() ?? Text::translate('COM_FTK_ERROR_APPLICATION_UNSPECIFIED_ERROR_TEXT')];

				header("Content-type: application/json; charset=utf-8");
			}
			else
			{
				echo ($e->getMessage() ?? Text::translate('COM_FTK_ERROR_APPLICATION_UNSPECIFIED_ERROR_TEXT'));
				exit;
			}*/

			// Prepare response depending on the value of $format.
			switch ($format)
			{
				case 'jsonp' :
					http_response_code( $e->getCode() ?: '500' );

					header("Content-type: text/javascript; charset=utf-8");	// is set in final switch/case block 'jsonp'

					echo sprintf('%s(%s)', $input->get('callback', 'jsonpCallback'), json_encode((array) $response, JSON_THROW_ON_ERROR));
					exit;
				break;

				case 'json' :
					http_response_code( $e->getCode() ?: '500' );

					header("Content-type: application/json; charset=utf-8");

					$response = ['error' => $e->getMessage() ?? Text::translate('COM_FTK_ERROR_APPLICATION_UNSPECIFIED_ERROR_TEXT')];
				break;

				default :
					echo ($e->getMessage() ?? Text::translate('COM_FTK_ERROR_APPLICATION_UNSPECIFIED_ERROR_TEXT'));
					exit;
			}
		}

		switch ($format)
		{
			case 'jsonp' :
//				header("Content-type: text/javascript; charset=utf-8");

				echo sprintf('%s(%s)', $input->get('callback', 'jsonpCallback'), json_encode((array) $response, JSON_THROW_ON_ERROR));
				exit;
			break;

			// JSON-encoding may throw exception(s). Hence, it is wrapped by a try/catch-block.
			case 'json' :
//				header("Content-type: application/json; charset=utf-8");	//   D O   N O T   S E T   this header here,
																			// because it breaks parsing JSON in the related Javascript function for the following reason:
																			// Uncaught SyntaxError: Unexpected token (...) in JSON at position 0
				try
				{
					echo json_encode($response, JSON_THROW_ON_ERROR);
				}
				catch (JsonException $e) {}

				exit;
			break;

			default :
				return $response;
		}
	}
}
