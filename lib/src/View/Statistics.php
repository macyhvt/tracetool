<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View;
use stdClass;
use function is_a;
use function property_exists;

/**
 * Class description
 */
class Statistics extends View
{
	use \Nematrack\Traits\View\Statistics;

	/**
	 * {@inheritdoc}
	 * @see View::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

		// Assign ref to HTTP Request object.
		$this->input = App::getInput();

		// Don't load display data when there's POST data to process.
		/* if (count($this->input->post->getArray()))
		{
			return;
		} */

		// Access control. Only registered and authenticated users can view content.
		if (!is_a($this->user, 'Nematrack\Entity\User'))
		{
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \Nematrack\Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Get date format configuration from user profile.
		$userProfile  = new Registry(UserHelper::getProfile($this->user));
		$localeConfig = $userProfile->extract('user.locale');
		$localeConfig = (is_a($localeConfig, 'Joomla\Registry\Registry') ? $localeConfig : new Registry);

		switch ($this->layout)
		{
			// Load list.
			// TODO - change to project.process.matrix
			case 'article.matrix' :
				$dateToday = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'
				// Get dateTo from $GET or set default date if not available.
				$dateFrom  = $this->input->getCmd('dateFrom') ?? $dateToday;
				// Get dateTo from $GET or set default date if not available.
				$dateTo    = $this->input->getCmd('dateTo')   ?? $dateFrom;
				$proNum    = $this->input->getAlnum('project');
				$qType     = $this->input->getWord('quality', 'good');

				$project   = (isset($proNum)
					? $this->model->getInstance('project', ['language' => $this->model->get('language')])->getProjectByNumber($proNum)
					: $this->model->getInstance('project', ['language' => $this->model->get('language')])->getItem(0)
				);

				$item = new Registry;
				$item->set('dateToday', $dateToday);
				$item->set('dateFrom',  $dateFrom);
				$item->set('dateTo',    $dateTo);
				$item->set('projects',  $this->model->getInstance('projects',  ['language' => $this->model->get('language')])->getList());
				$item->set('project',   $project);
				$item->set('articles',  $this->model->getInstance('articles',  ['language' => $this->model->get('language')])->getArticlesByProjectID($project->get('proID', 0)));
				$item->set('processes', $this->model->getInstance('processes', ['language' => $this->model->get('language')])->getList());
				$item->set('quality',   $qType);

				// Assign ref to prepared content.
				$this->data = $item;
			break;

			// Load list.
			// TODO - change to project.process.parts
			case 'article.process.parts' :
				$dateToday = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'

				// Get dateFrom from $GET or set default date if not available.
				$dateFrom  = $this->input->getCmd('df');
				$dateFrom  = !empty($dateFrom) ? $dateFrom :  $dateToday;	// FIXME - backend can only handle 'd.m.Y'

				// Get dateTo from $GET or set default date if not available.
				$dateTo    = $this->input->getCmd('dt', $dateToday);	// FIXME - backend can only handle 'd.m.Y'

				// Get timeFrom from $GET or set default time if not available.
				$timeFrom  = $this->input->getString('tf');
//				$timeFrom  = !empty($timeFrom) ? $timeFrom : $today->format('H:i');
				$timeFrom  = !empty($timeFrom) ? $timeFrom : '00:00:01';
				// Fix format if meridian is present.
				$timeFrom  = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeFrom)) ? (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeFrom;
				$timeFrom  = is_a($timeFrom, 'DateTime') ? $timeFrom : (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE)));
				$timeFrom  = $timeFrom->format('H:i');	// DE
//				$timeFrom  = $timeFrom->format('H\hi');	// FR
//				$timeFrom  = $timeFrom->format('g:i');	// EN, HU

				// Get timeTo from $GET or set default time if not available.
				$timeTo    = $this->input->getString('tt');
//				$timeTo    = $this->input->getString('tt', $today->format('H:i'));
				$timeTo    = !empty($timeTo) ? $timeTo : '23:59:59';
				// Fix format if meridian is present.
				$timeTo    = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeTo)) ? (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeTo;
				$timeTo    = is_a($timeTo, 'DateTime') ? $timeTo : (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE)));
				$timeTo    = $timeTo->format('H:i');		// DE
//				$timeTo    = $timeTo->format('H\hi');	// FR
//				$timeTo    = $timeTo->format('g:i');		// EN, HU

				$proNum    = $this->input->getAlnum('project');
				$artID     = $this->input->getInt( $this->getInstance('article', ['language' => $this->language])->getIdentificationKey(), $this->input->getInt('aid') );
				$procID    = $this->input->getInt( $this->getInstance('process', ['language' => $this->language])->getIdentificationKey(), $this->input->getInt('pid') );
				$qType     = $this->input->getWord('quality', 'good');
				$filter    = $this->input->getString('filter', ListModel::FILTER_ACTIVE);
				$order     = $this->input->getString('order', 'timestamp');	// column to oder by
				$sort      = $this->input->getString('sort',  'ASC');		// direction to oder to

				$project   = (isset($proNum)
					? $this->model->getInstance('project', ['language' => $this->model->get('language')])->getProjectByNumber($proNum)
					: $this->model->getInstance('project', ['language' => $this->model->get('language')])->getItem(0)
				);

				$process  = $this->model->getInstance('process', ['language' => $this->model->get('language')])->getItem($procID);
				$article  = $this->model->getInstance('article', ['language' => $this->model->get('language')])->getItem($artID);
				$parts    = $this->model->getInstance('article', ['language' => $this->model->get('language')])->getPartsPerProcess([
					$article->getPrimaryKeyName() =>   $article->get( $article->getPrimaryKeyName() ),
					'procIDs'  => [ $process->get( $process->getPrimaryKeyName() ) ],
					'dateFrom' => $dateFrom,
					'dateTo'   => $dateTo,
					'timeFrom' => $timeFrom,
					'timeTo'   => $timeTo,
					'quality'  => $qType,
					'order'    => $order,
					'sort'     => $sort
				]);

				$data = new Registry;
				$data->set('dateToday', $dateToday);
				$data->set('dateFrom',  $dateFrom);
				$data->set('dateTo',    $dateTo);
				$data->set('timeFrom',  $timeFrom);
				$data->set('timeTo',    $timeTo);
				$data->set('quality',   $qType);
				$data->set('filter',    $filter);
				$data->set('order',     $order);
				$data->set('sort',      $sort);
				$data->set('project',   $project);
				$data->set('article',   $article);
				$data->set('process',   $process);
				$data->set('parts',     $parts);

				// Assign ref to loaded item.
				$this->data = $data;
			break;

			// Load list.
			// TEMP DISABLED UNTIL DECISION ON HOW TO DISPLAY
			case 'errors.summary' :
				/*$date   = $this->input->getCmd('date') ?? (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('d.m.Y');
				$errors = $this->model->getErrorStats($date);

				$list = new Registry;
				$list->set('date',   $date);
				$list->set('errors', $errors);

				// Assign ref to loaded list data.
				$this->list = $list;*/
				$this->list = null;
			break;

			/*// Load list.
			case 'project.monitor' :
				$proNum     = $this->input->getAlnum('project');
				$collection = $this->input->getWord('collection');
				$collection = (isset($collection)) ? mb_strtolower($collection) : $collection;
				$pids       = $this->input->get('pids', [], 'ARRAY');
				$today      = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('d.m.Y');
				$dateFrom   = $this->input->getCmd('dateFrom') ?? $today;
				$dateTo     = $this->input->getCmd('dateTo')   ?? $today;

				$project   = (isset($proNum)
					? $this->model->getInstance('project', ['language' => $this->model->get('language')])->getProjectByNumber($proNum)
					: $this->model->getInstance('project', ['language' => $this->model->get('language')])->getItem(0)
				);

				if (!is_a($project, 'Nematrack\Entity\Project') || !$project->get('number'))
				{
					// TODO - translate
					throw new Exception(sprintf('Unknown project: %s', $proNum), 404);
				}

				$processes  = $this->model->getInstance('processes', ['language' => $this->model->get('language')])->getList();
				$pidAbbrMap = array_combine($pids, array_fill(0, count($pids), null));

				foreach ($pidAbbrMap as $pid => &$value)
				{
					$value = ArrayHelper::getValue((array) ArrayHelper::getValue($processes, $pid), 'abbreviation');
				}

				$stats = $this->model->getOutputMonitorData($project->get('proID'), $dateFrom, $dateTo, $pidAbbrMap, $pids);

				$item = new Registry;
				$item->set('dateFrom', $dateFrom);
				$item->set('dateTo',   $dateTo);
				$item->set('project',  $project);
//				$item->set('quality',  $qType);
				$item->set('stats',    $stats);

				// Assign ref to loaded item.
				$this->data = $item;

				// Clean data (replace '|' with ';')
				foreach ($stats as $colName => &$col)
				{
//					if (isset($collection) && !preg_match("/{$collection}/i", $colName))
					if (isset($collection) && $colName != $collection)
					{
						continue;
					}

					$writer = WriterEntityFactory::createWriterFromFile(FTKPATH_TEMP . DIRECTORY_SEPARATOR . sprintf('%s-%s.csv', mb_strtoupper($project->get('number')), $colName));
					$writer->openToFile(FTKPATH_TEMP . DIRECTORY_SEPARATOR . sprintf('%s-%s.csv', mb_strtoupper($project->get('number')), $colName));

					$i = 0;
					array_walk($col, function(&$line, $key) use(&$i, &$writer)
					{
						$line = trim($line);
//						$line = preg_replace('/(^\||\s+|\|$)/i', '', $line);
						$line = preg_replace('/\|\s*([a-z0-9]+)\s*\|/i',"|$1|", $line);
						$line = preg_replace('/\|\s+\|/i','|0|', $line);
						$line = preg_replace('/\|\s+\|/i','|0|', $line);
						$line = preg_replace('/(\|\s+|\s+\|)/i', '|', trim($line));
						$line = preg_replace('/(^\||\|$)/i', '', trim($line));
						$line = str_replace('|', ';', $line);
						$line = (!preg_match('/[a-z0-9]+/i', $line)) ? '' : $line;
//						$line = preg_replace('/;;/i', ';0;', $line);
//						$line = preg_replace('/;;/i', ';0;', $line);
//						$line = preg_replace('/^;/i', '0;', $line);
//						$line = preg_replace('/;$/i', ';0', $line);

						// Stream into CSV-file.
						if (strlen($line))
						{
							$writer->addRow( WriterEntityFactory::createRowFromArray(explode(';', sprintf('%s;%s', $key, $line))) );
						}

						$i += 1;
					});
//					$col = array_filter($col);

					$writer->close();
				}
			break;*/

			// Load list.
			case 'project.monitoring' :
				$dateToday = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'
				// Get dateFrom from $GET or set default date if not available.
				$dateFrom  = $this->input->getCmd('dateFrom');
				// Get dateTo from $GET or set default date if not available.
				$dateTo    = $this->input->getCmd('dateTo');

				$processes = $this->model->getInstance('processes', ['language' => $this->model->get('language')])->getList([
					'language' => $this->model->get('language'),
					'filter'   => ListModel::FILTER_ALL,
					'catalog'  => false,
					'params'   => false
				]);
				$projects  = $this->model->getInstance('projects',  ['language' => $this->model->get('language')])->getList([
					'filter' => ListModel::FILTER_ALL
				]);

				$data = new Registry;
				$data->set('dateToday', $dateToday);
				$data->set('dateFrom',  $dateFrom);
				$data->set('dateTo',    $dateTo);
				$data->set('projects',  $projects);
				$data->set('processes', $processes);

				// Assign ref to loaded data.
				$this->data = $data;
			break;

			// Load list.
			case 'tracking.processes' :
				$dateToday    = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'

				// Get dateFrom from $GET or set default date if not available.
				$dateFrom = $this->input->getCmd('df');
				$dateFrom = !empty($dateFrom) ? $dateFrom :  $dateToday;	// FIXME - backend can only handle 'd.m.Y'

				// Get dateTo from $GET or set default date if not available.
				$dateTo   = $this->input->getCmd('dt', $dateToday);	// FIXME - backend can only handle 'd.m.Y'

				// Get timeFrom from $GET or set default time if not available.
				$timeFrom = $this->input->getString('tf');
//				$timeFrom = !empty($timeFrom) ? $timeFrom : $today->format('H:i');
				$timeFrom = !empty($timeFrom) ? $timeFrom : '00:00:01';
				// Fix format if meridian is present.
				$timeFrom = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeFrom)) ? (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeFrom;
				$timeFrom = is_a($timeFrom, 'DateTime') ? $timeFrom : (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE)));
				$timeFrom = $timeFrom->format('H:i');	// DE
//				$timeFrom = $timeFrom->format('H\hi');	// FR
//				$timeFrom = $timeFrom->format('g:i');	// EN, HU

				// Get timeTo from $GET or set default time if not available.
				$timeTo   = $this->input->getString('tt');
//				$timeTo   = $this->input->getString('tt', $today->format('H:i'));
				$timeTo   = !empty($timeTo) ? $timeTo : '23:59:59';
				// Fix format if meridian is present.
				$timeTo   = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeTo)) ? (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeTo;
				$timeTo   = is_a($timeTo, 'DateTime') ? $timeTo : (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE)));
				$timeTo   = $timeTo->format('H:i');		// DE
//				$timeTo   = $timeTo->format('H\hi');	// FR
//				$timeTo   = $timeTo->format('g:i');		// EN, HU

				// Fetch data.
				$filter    = $this->input->getString('filter', ListModel::FILTER_ACTIVE);
				$order     = $this->input->getString('order');			// column to oder by
				$sort      = $this->input->getString('sort', 'ASC');	// direction to oder to

				// Fetch active processes.
				$processes = $this->model->getInstance('processes', ['language' => $this->model->get('language')])->getList([
					'language' => $this->model->get('language'),
					'filter'   => ListModel::FILTER_ACTIVE,	// Force only active processes to be displayed. There's no need to display blocked ones.
					'order'    => 'name',	// Force processes ordering on the name column
					'sort'     => 'ASC'		// Force processes sorting in ascending order
				]);

				// Fetch for every process its statistics.
				$statsModel = $this->model->getInstance('statistics', ['language' => $this->model->get('language')]);
				$list       = [];

				foreach ($processes as $id => $arr)
				{
					// Return value looks like: arrResult: {"first":"2022-05-02 09:38:11","last":"2022-06-10 12:37:46","total":3031,"breaks":2144}
					$list[$id] = $statsModel->getProcessStats([
						'procID'   => $id,			// FIXME - get primary key name of process entity via Entity::getPrimaryKeyName()
						'dateFrom' => $dateFrom,
						'dateTo'   => $dateTo,
						'timeFrom' => $timeFrom,
						'timeTo'   => $timeTo
					]);
				}

				// Apply list filter.
				switch ($filter)
				{
					case ListModel::FILTER_ACTIVE :		// 0 - skip processes with empty stats
					default :
						array_walk($list, function($arr, $id) use(&$processes)
						{
							if (empty($arr))
							{
								unset($processes[$id]);
							}
							else
							{
								$processes[$id]['stats'] = $arr;
							}
						});
					break;

					case ListModel::FILTER_LOCKED :		// 1
					break;

					case ListModel::FILTER_ARCHIVED :	// 2
					break;

					case ListModel::FILTER_ALL :		// 3
					break;

					case ListModel::FILTER_NEMA :       //111 for Nematech
                        array_walk($list, function($arr, $id) use(&$processes)
                        {
                            //echo "<pre>"; print_r($processes);
                            if (empty($arr))
                            {
                                unset($processes[$id]);
                            }
                            else
                            {
                                $processes[$id]['stats'] = $arr;


                            }
                        });


                        break;
                    case ListModel::FILTER_FRO : //112 for Froetek
                        array_walk($list, function($arr, $id) use(&$processes)
                        {
                            //echo "<pre>"; print_r($processes);
                            if (empty($arr))
                            {
                                unset($processes[$id]);
                            }
                            else
                            {
                                $processes[$id]['stats'] = $arr;
                            }
                        });

                        break;
                    case ListModel::FILTER_NEMEC :	// 113 for Nemectek
                        array_walk($list, function($arr, $id) use(&$processes)
                        {
                            //echo "<pre>"; print_r($processes);
                            if (empty($arr))
                            {
                                unset($processes[$id]);
                            }
                            else
                            {
                                $processes[$id]['stats'] = $arr;
                            }
                        });
                        break;

					case ListModel::FILTER_EMPTY :		// 4 - skip processes with non-empty stats
						array_walk($list, function($arr, $id) use(&$processes)
						{
							if (!empty($arr))
							{
								unset($processes[$id]);
							}
							else
							{
								$processes[$id]['stats'] = $arr;
							}
						});
					break;
				}

				$data = new Registry;
				$data->set('dateToday', $dateToday);
				$data->set('dateFrom',  $dateFrom);
				$data->set('dateTo',    $dateTo);
				$data->set('timeFrom',  $timeFrom);
				$data->set('timeTo',    $timeTo);
				$data->set('filter',    $filter);
				$data->set('order',     $order);
				$data->set('sort',      $sort);
				$data->set('processes', $processes);

				// Assign ref to loaded data.
				$this->data = $data;
			break;

			// Fetch item.
			case 'tracking.process' :
				$dateToday = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'

				// Get dateFrom from $GET or set default date if not available.
				$dateFrom  = $this->input->getCmd('df');
				$dateFrom  = !empty($dateFrom) ? $dateFrom :  $dateToday;	// FIXME - backend can only handle 'd.m.Y'

				// Get dateTo from $GET or set default date if not available.
				$dateTo    = $this->input->getCmd('dt', $dateToday);	// FIXME - backend can only handle 'd.m.Y'

				// Get timeFrom from $GET or set default time if not available.
				$timeFrom  = $this->input->getString('tf');
//				$timeFrom  = !empty($timeFrom) ? $timeFrom : $today->format('H:i');
				$timeFrom  = !empty($timeFrom) ? $timeFrom : '00:00:01';
				// Fix format if meridian is present.
				$timeFrom  = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeFrom)) ? (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeFrom;
				$timeFrom  = is_a($timeFrom, 'DateTime') ? $timeFrom : (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE)));
				$timeFrom  = $timeFrom->format('H:i');	// DE
//				$timeFrom  = $timeFrom->format('H\hi');	// FR
//				$timeFrom  = $timeFrom->format('g:i');	// EN, HU

				// Get timeTo from $GET or set default time if not available.
				$timeTo    = $this->input->getString('tt');
//				$timeTo    = $this->input->getString('tt', $today->format('H:i'));
				$timeTo    = !empty($timeTo) ? $timeTo : '23:59:59';
				// Fix format if meridian is present.
				$timeTo    = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeTo)) ? (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeTo;
				$timeTo    = is_a($timeTo, 'DateTime') ? $timeTo : (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE)));
				$timeTo    = $timeTo->format('H:i');		// DE
//				$timeTo    = $timeTo->format('H\hi');	// FR
//				$timeTo    = $timeTo->format('g:i');		// EN, HU

				// Fetch data.
				$filter    = $this->input->getString('filter', ListModel::FILTER_ACTIVE);
				$order     = $this->input->getString('order', 'name');	// column to oder by
				$sort      = $this->input->getString('sort',  'ASC');	// direction to oder to

				$procID    = $this->input->getInt($this->getInstance('process', ['language' => $this->language])->getIdentificationKey(), $this->input->getInt('pid'));
				$process   = $this->model->getInstance('process', ['language' => $this->model->get('language')])->getItem($procID);
				$articles  = $this->model->getProcessArticles([
					$process->getPrimaryKeyName() => $process->get($process->getPrimaryKeyName()),
					'language' => $this->model->get('language'),
					'dateFrom' => $dateFrom,
					'dateTo'   => $dateTo,
					'timeFrom' => $timeFrom,
					'timeTo'   => $timeTo,
					'filter'   => $filter,	// Force only active processes to be displayed. There's no need to display blocked ones.
					'order'    => $order,
					'sort'     => $sort
				]);

				$articleModel = $this->model->getInstance('article',  ['language' => $this->model->get('language')]);

				array_walk($articles, function(&$arr) use(&$articleModel)
				{
					$article = $articleModel->getItem(ArrayHelper::getValue($arr, 'artID'));

					$drawing = $article->get('drawing');
					$drawing = (isset($drawing))
						? (new Registry(json_decode((string) $article->get('drawing'), null, 512, JSON_THROW_ON_ERROR)))->toObject()
						: new stdClass;

					$arr['drawing'] = $drawing;

				    $customerDrawing = $article->get('customerDrawing');
				    $customerDrawing = (isset($customerDrawing))
					    ? (new Registry(json_decode((string) $article->get('customerDrawing'), null, 512, JSON_THROW_ON_ERROR)))->toObject()
					    : new stdClass;

					$arr['customerDrawing'] = $customerDrawing;
				});

				$data = new Registry;
				$data->set('dateToday', $dateToday);
				$data->set('dateFrom',  $dateFrom);
				$data->set('dateTo',    $dateTo);
				$data->set('timeFrom',  $timeFrom);
				$data->set('timeTo',    $timeTo);
				$data->set('filter',    $filter);
				$data->set('order',     $order);
				$data->set('sort',      $sort);
				$data->set('process',   $process);
				$data->set('articles',  $articles);

				// Assign ref to loaded item.
				$this->data = $data;
			break;
			case 'tracking.badprocess' :
                $dateToday = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'

                // Get dateFrom from $GET or set default date if not available.
                $dateFrom  = $this->input->getCmd('df');
                $dateFrom  = !empty($dateFrom) ? $dateFrom :  $dateToday;	// FIXME - backend can only handle 'd.m.Y'

                // Get dateTo from $GET or set default date if not available.
                $dateTo    = $this->input->getCmd('dt', $dateToday);	// FIXME - backend can only handle 'd.m.Y'

                // Get timeFrom from $GET or set default time if not available.
                $timeFrom  = $this->input->getString('tf');
//				$timeFrom  = !empty($timeFrom) ? $timeFrom : $today->format('H:i');
                $timeFrom  = !empty($timeFrom) ? $timeFrom : '00:00:01';
                // Fix format if meridian is present.
                $timeFrom  = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeFrom)) ? (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeFrom;
                $timeFrom  = is_a($timeFrom, 'DateTime') ? $timeFrom : (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE)));
                $timeFrom  = $timeFrom->format('H:i');	// DE
//				$timeFrom  = $timeFrom->format('H\hi');	// FR
//				$timeFrom  = $timeFrom->format('g:i');	// EN, HU

                // Get timeTo from $GET or set default time if not available.
                $timeTo    = $this->input->getString('tt');
//				$timeTo    = $this->input->getString('tt', $today->format('H:i'));
                $timeTo    = !empty($timeTo) ? $timeTo : '23:59:59';
                // Fix format if meridian is present.
                $timeTo    = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeTo)) ? (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeTo;
                $timeTo    = is_a($timeTo, 'DateTime') ? $timeTo : (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE)));
                $timeTo    = $timeTo->format('H:i');		// DE
//				$timeTo    = $timeTo->format('H\hi');	// FR
//				$timeTo    = $timeTo->format('g:i');		// EN, HU

                // Fetch data.
                $filter    = $this->input->getString('filter', ListModel::FILTER_ACTIVE);
                $order     = $this->input->getString('order', 'name');	// column to oder by
                $sort      = $this->input->getString('sort',  'ASC');	// direction to oder to

                $procID    = $this->input->getInt($this->getInstance('process', ['language' => $this->language])->getIdentificationKey(), $this->input->getInt('pid'));
                $process   = $this->model->getInstance('process', ['language' => $this->model->get('language')])->getItem($procID);
                $articles  = $this->model->getbadProcessArticles([
                    $process->getPrimaryKeyName() => $process->get($process->getPrimaryKeyName()),
                    'language' => $this->model->get('language'),
                    'dateFrom' => $dateFrom,
                    'dateTo'   => $dateTo,
                    'timeFrom' => $timeFrom,
                    'timeTo'   => $timeTo,
                    'filter'   => $filter,	// Force only active processes to be displayed. There's no need to display blocked ones.
                    'order'    => $order,
                    'sort'     => $sort
                ]);

                $articleModel = $this->model->getInstance('article',  ['language' => $this->model->get('language')]);

                array_walk($articles, function(&$arr) use(&$articleModel)
                {
                    $article = $articleModel->getItem(ArrayHelper::getValue($arr, 'artID'));

                    $drawing = $article->get('drawing');
                    $drawing = (isset($drawing))
                        ? (new Registry(json_decode((string) $article->get('drawing'), null, 512, JSON_THROW_ON_ERROR)))->toObject()
                        : new stdClass;

                    $arr['drawing'] = $drawing;

                    $customerDrawing = $article->get('customerDrawing');
                    $customerDrawing = (isset($customerDrawing))
                        ? (new Registry(json_decode((string) $article->get('customerDrawing'), null, 512, JSON_THROW_ON_ERROR)))->toObject()
                        : new stdClass;

                    $arr['customerDrawing'] = $customerDrawing;
                });

                $data = new Registry;
                $data->set('dateToday', $dateToday);
                $data->set('dateFrom',  $dateFrom);
                $data->set('dateTo',    $dateTo);
                $data->set('timeFrom',  $timeFrom);
                $data->set('timeTo',    $timeTo);
                $data->set('filter',    $filter);
                $data->set('order',     $order);
                $data->set('sort',      $sort);
                $data->set('process',   $process);
                $data->set('articles',  $articles);

                // Assign ref to loaded item.
                $this->data = $data;
                break;
            case 'tracking.goodprocess' :
                $dateToday = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($localeConfig->get('date', 'd.m.Y'));	// FIXME - backend can only handle 'd.m.Y'

                // Get dateFrom from $GET or set default date if not available.
                $dateFrom  = $this->input->getCmd('df');
                $dateFrom  = !empty($dateFrom) ? $dateFrom :  $dateToday;	// FIXME - backend can only handle 'd.m.Y'

                // Get dateTo from $GET or set default date if not available.
                $dateTo    = $this->input->getCmd('dt', $dateToday);	// FIXME - backend can only handle 'd.m.Y'

                // Get timeFrom from $GET or set default time if not available.
                $timeFrom  = $this->input->getString('tf');
//				$timeFrom  = !empty($timeFrom) ? $timeFrom : $today->format('H:i');
                $timeFrom  = !empty($timeFrom) ? $timeFrom : '00:00:01';
                // Fix format if meridian is present.
                $timeFrom  = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeFrom)) ? (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeFrom;
                $timeFrom  = is_a($timeFrom, 'DateTime') ? $timeFrom : (new DateTime($timeFrom, new DateTimeZone(FTKRULE_TIMEZONE)));
                $timeFrom  = $timeFrom->format('H:i');	// DE
//				$timeFrom  = $timeFrom->format('H\hi');	// FR
//				$timeFrom  = $timeFrom->format('g:i');	// EN, HU

                // Get timeTo from $GET or set default time if not available.
                $timeTo    = $this->input->getString('tt');
//				$timeTo    = $this->input->getString('tt', $today->format('H:i'));
                $timeTo    = !empty($timeTo) ? $timeTo : '23:59:59';
                // Fix format if meridian is present.
                $timeTo    = preg_match('/^\d.* ?[AP]M$/i', mb_strtolower($timeTo)) ? (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE))) : $timeTo;
                $timeTo    = is_a($timeTo, 'DateTime') ? $timeTo : (new DateTime($timeTo, new DateTimeZone(FTKRULE_TIMEZONE)));
                $timeTo    = $timeTo->format('H:i');		// DE
//				$timeTo    = $timeTo->format('H\hi');	// FR
//				$timeTo    = $timeTo->format('g:i');		// EN, HU

                // Fetch data.
                $filter    = $this->input->getString('filter', ListModel::FILTER_ACTIVE);
                $order     = $this->input->getString('order', 'name');	// column to oder by
                $sort      = $this->input->getString('sort',  'ASC');	// direction to oder to

                $procID    = $this->input->getInt($this->getInstance('process', ['language' => $this->language])->getIdentificationKey(), $this->input->getInt('pid'));
                $process   = $this->model->getInstance('process', ['language' => $this->model->get('language')])->getItem($procID);
                $articles  = $this->model->getgoodProcessArticles([
                    $process->getPrimaryKeyName() => $process->get($process->getPrimaryKeyName()),
                    'language' => $this->model->get('language'),
                    'dateFrom' => $dateFrom,
                    'dateTo'   => $dateTo,
                    'timeFrom' => $timeFrom,
                    'timeTo'   => $timeTo,
                    'filter'   => $filter,	// Force only active processes to be displayed. There's no need to display blocked ones.
                    'order'    => $order,
                    'sort'     => $sort
                ]);

                $articleModel = $this->model->getInstance('article',  ['language' => $this->model->get('language')]);

                array_walk($articles, function(&$arr) use(&$articleModel)
                {
                    $article = $articleModel->getItem(ArrayHelper::getValue($arr, 'artID'));

                    $drawing = $article->get('drawing');
                    $drawing = (isset($drawing))
                        ? (new Registry(json_decode((string) $article->get('drawing'), null, 512, JSON_THROW_ON_ERROR)))->toObject()
                        : new stdClass;

                    $arr['drawing'] = $drawing;

                    $customerDrawing = $article->get('customerDrawing');
                    $customerDrawing = (isset($customerDrawing))
                        ? (new Registry(json_decode((string) $article->get('customerDrawing'), null, 512, JSON_THROW_ON_ERROR)))->toObject()
                        : new stdClass;

                    $arr['customerDrawing'] = $customerDrawing;
                });

                $data = new Registry;
                $data->set('dateToday', $dateToday);
                $data->set('dateFrom',  $dateFrom);
                $data->set('dateTo',    $dateTo);
                $data->set('timeFrom',  $timeFrom);
                $data->set('timeTo',    $timeTo);
                $data->set('filter',    $filter);
                $data->set('order',     $order);
                $data->set('sort',      $sort);
                $data->set('process',   $process);
                $data->set('articles',  $articles);

                // Assign ref to loaded item.
                $this->data = $data;
                break;

			// Fetch item.
			/*case 'tracking.article' :
				$date     = $this->input->getCmd('date') ?? (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('d.m.Y');
				$artID    = $this->input->getInt('aid');
				$procID   = $this->input->getInt('pid');

				$article  = $this->model->getInstance('article', ['language' => $this->model->get('language')])->getItem($artID);
				$process  = $this->model->getInstance('process', ['language' => $this->model->get('language')])->getItem($procID);
				$parts    = $this->model->getProcessParts((int) $process->get('procID'), (int) $article->get('artID'), $date);

				$item = new Registry;
				$item->set('date',     $date);
				$item->set('article',  $article);
				$item->set('process',  $process);
				$item->set('parts',    $parts);

				// Assign ref to loaded item.
				$this->item = $item;
			break;*/
		}
	}

	/**
	 * {@inheritdoc}
	 * @see View::getRoute
	 */
	public function getRoute() : string
	{
		$route = mb_strtolower( sprintf( 'index.php?hl=%s&view=%s', $this->get('language'), $this->get('name') ) );

		return UriHelper::fixURL($route);
	}

	/**
	 * Saves a user's individual process matrix configuration.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveMatrixConfig(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post     = $this->input->post->getArray();
		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		$uid    = $this->input->post->getInt('user', 0);
		$config = ArrayHelper::getValue($post, 'config', [], 'ARRAY');

		// Save data.
		$status = $this->model->getInstance('user', ['language' => $this->model->get('language')])->updateProfile(
			$uid,
			[
				'uid'     => $uid,
				'profile' => $config
			]
		);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.

			// Error messages are set in model

			// http_response_code('500');
		}
		else
		{
			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
			]);

			// http_response_code('200');

			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}
		}

		// Add the page to return to, that was sent via POST.
		// $redirect->setVar('return', $return);	// Don't set it, when it was loaded into new Uri instead of $redirect, as that would redirect to itself.

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		header('Location: ' . $redirect->toString());
		exit;
	}
}
