<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use FilesystemIterator;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Factory;
use  \Helper\FilesystemHelper;
use  \Helper\UriHelper;
use  \Messager;
use  \Model;
use  \Text;
use  \View;
use  \View\Lizt as ListView;
use RecursiveDirectoryIterator;
use RuntimeException;
use Smalot\PdfParser\Parser;
use UnexpectedValueException;

/**
 * Class description
 */
class Tools extends ListView
{
	/**
	 * {@inheritdoc}
	 * @see View::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

		// Don't load display data when there's POST data to process.
		/*if (count($_POST))
		{
			return;
		}*/

		// Prepare view for rendering.
		$this->prepareDocument();

		// Access control.


		switch ($this->get('layout'))
		{
			case 'batch.upload.images' :
				// Define the root look-up path.
				$rootPath = App::getBatchUploadPath();

				// Add reference.
				$list['rootPath'] = $rootPath;

				// Define the traverse depth.
				$traverseDepth = 1;

				// Add reference.
				$list['traverseDepth'] = $traverseDepth;

				// Fetch directory contents.
				$rootDirs = FilesystemHelper::getDirectoryList([
					'path'  => $rootPath,
					'depth' => $traverseDepth
				]);

				// Add reference.
				$list['rootDirs'] = $rootDirs;

				// Check for selected sub-path.
				$browsePath = $this->input->getUrl('path');
				$browsePath = isset($browsePath) ? base64_decode($browsePath) : '';

				// Add reference.
				$list['browsePath'] = $browsePath;

				// Read directory contents.
				if ($this->get('taskName') == 'browse')
				{
					$rootPath = null;
					$find     = basename($browsePath);
					$found    = false;
					$files    = [];

					foreach ($list['rootDirs'] as $collection)
					{
						if (ArrayHelper::getValue($collection, $find))
						{
							$found    = true;
							$rootPath = ArrayHelper::getValue($collection, $find);

							break;
						}
					}

					// Fetch contents.
					if ($found && isset($rootPath))
					{
						$files = FilesystemHelper::getFilesInDirectory([
//							'path'  => '/path/to/dir',  // @test (maybe this can be used in a Test class?)
							'paths' => [$rootPath],
//							'type'  => '*.bmp',         // @test (maybe this can be used in a Test class?)
							'types' => ['*.jpg','*.jpeg','*.png']
						]);
					}

					// Add reference.
					$list['browsePathContents'] = $files;
				}

				// Assign ref to loaded list data.
				$this->list = $list;
			break;

			case 'sort.drawings' :
				$this->sortDrawings();
			exit;
		}
	}

	/**
	 * {@inheritdoc}
	 * @see View::getRoute
	 */
	public function getRoute(): string
	{
		$route = mb_strtolower( sprintf( 'index.php?hl=%s&view=%s', $this->get('language'), $this->get('name') ) );

		return UriHelper::fixURL($route);
	}

	/**
	 * Moves images from a given directory to the media-files store sorting into process-specific sub-directories.
	 *
	 * @param   string|null $redirect  The URI to redirect after processing completed.
	 *
	 * @return  void
	 */
	public function handleBatchUpload(string $redirect = null) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$return = base64_decode($this->input->post->getBase64('return', ''));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = (!empty($return)) ? new Uri($return) : new Uri(static::getReferer());
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		$abbr  = $this->input->getBase64('abbr');
		$path  = base64_decode($this->input->getBase64('path'));
		$files = $this->input->get('files', [], 'STRING');

		if (isset($files) && is_countable($files))
		{
			$status = $this->model->batchUploadProcessImages([
				'abbreviation' => $abbr,
				'path'         => $path,
				'files'        => $files
			]);

			// Everything's fine. Upload was successful.
			if (0 !== ($cnt = count($status)))
			{
				// Give user feedback.
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate(
						mb_strtoupper(
							sprintf('COM_FTK_SYSTEM_MESSAGE_%s_UPLOAD_FAILED_TEXT', $cnt == 1 ? 'FILE' : 'FILES')
						),
						$this->language
					)
				]);
			}
			// Something went wrong.
			else
			{
				// Delete input directory.
				FilesystemHelper::deleteDirectory($path);

				// Give user feedback.
				Messager::setMessage([
					'type' => 'success',
					'text' => Text::translate(
						mb_strtoupper(
							sprintf('COM_FTK_SYSTEM_MESSAGE_%s_UPLOAD_SUCCESSFUL_TEXT', count($files) == 1 ? 'FILE' : 'FILES')
						),
						$this->language
					)
				]);

				// Redirect the user to 1 level above processed directory.
				$redirect->delVar('task');
				$redirect->delVar('path');
			}
		}

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Add description...
	 *
	 * @param   string|null $redirect  The URI to redirect after processing completed.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	public function handleUploadedPicture(string $redirect = null) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$return   = base64_decode($this->input->post->getBase64('return', ''));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = (!empty($return)) ? new Uri($return) : new Uri(static::getReferer());
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Get process.
		$process  = $this->model->getInstance('process', ['language' => $this->model->get('language')])->getItem($this->input->getInt('pid'));

		// Get part.
		$part = $this->model->getInstance('part', ['language' => $this->model->get('language')])->getItem($this->input->getInt('id'));
		$type = $part->get('type');
		$code = $part->get('trackingcode');

		// Get path of file to be uploaded.
		$filePath = $this->input->getUrl('filePath');

		// Sanitize input path.
		$filePath = new Uri($filePath);

		// We accept internal files only.
		$filePath = $filePath->getScheme() ? $filePath->getPath() : $filePath->toString();

		// Fix slashes.
		$filePath = Path::clean($filePath);

		// It might be the file already includes the absolute path. Remove it.
		$filePath = str_ireplace(FTKPATH_BASE, '', $filePath);

		// The file path must be an absolute system path.
		$filePath = FTKPATH_BASE . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR);

		// Get extension of file to be uploaded (expected for the new file name).
		$fileExt  = explode('.', '' . $filePath);
		$fileExt  = array_pop($fileExt);

		// Define name of target file.
		$fileName = sprintf('%s@%s.%s', $code, $type, $fileExt);

		// Prepare path to move uploaded file to (required by the batch-uploader).
		$storePath = implode(DIRECTORY_SEPARATOR, [
			App::getBatchUploadPath(),
			$process->get('abbreviation'),
			$part->get('type')
		]);
		$storePath = FilesystemHelper::fixPath($storePath);

		// Inject process abbreviation (required by the batch-uploader).
		$this->input->set('abbr',  $process->get('abbreviation'));
		$this->input->set('path',  base64_encode($storePath));
		$this->input->set('files', [$fileName]);

		// Free memory.
		unset($process);
		unset($part);

		// Create store path (required by the batch-uploader).

		// Check if store location exists or create it.
		if (!is_dir($storePath))
		{
			if (false === mkdir($storePath, 0755, true))
			{
				throw new Exception(Text::translate('Directory could not be created.', $this->language));
			}
		}

		// Check if store location exists or create it.
		if (!is_readable($storePath))
		{
			throw new Exception(Text::translate('Asset directory created but not readable.', $this->language));
		}

		// Check if store location exists or create it.
		if (!is_writable($storePath))
		{
			throw new Exception(Text::translate('Asset directory created but not writable.', $this->language));
		}

		// Move uploaded file to the batch-upload path.
		if (rename($filePath, $storePath . DIRECTORY_SEPARATOR . $fileName))
		{
			// Delete temporary directory.
			rmdir(pathinfo($filePath, PATHINFO_DIRNAME));
		}

		// Done with validation and moving the temporary file. Now trigger the batch-uploader.

		/*// function handleBatchUpload expects the following GET-data:
		input: Array
		(
		    ...    => ...
		    [abbr] => gnd
		    [path] => L3d3dy9odGRvY3MvdzAxYjE4MzAvcHJvamVjdHMvZGV2Lm5lbWF0cmFjay5jb20vdG1wL2JhdGNoLXVwbG9hZC9nbmQvQUFBLkJCQi5DQy5DQ0NDQy4wMDA=
		    [path] => /www/htdocs/w01b1830/projects/dev. .com/tmp/batch-upload/gnd/AAA.BBB.CC.CCCCC.000
		    [files] => Array
		        (
		            [0] => 009-3G2-3GB@AAA.BBB.CC.CCCCC.000.jpg
		        )
		)*/

		$this->handleBatchUpload($redirect);
	}

	/**
	 * {@inheritdoc}
	 * @see \ \View\List::checkAccess()
	 */
	protected function checkAccess() : void
	{
		parent::checkAccess();

		// If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \ \Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#2)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}
	}

	// Sorts drawings on the server from root folder into article subdirectories.
	protected function sortDrawings() : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// @debug
		if (!$this->user->isProgrammer()) :
			return;
		else :
//			echo '<pre style="color:blue">' . print_r(__METHOD__, true) . '</pre>';
		endif;

		// Get a logger.
		$logger = Factory::getLogger([
			'context' => get_class($this),
			'type'    => 'rotate',
			'path'    => FTKPATH_LOGS . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'tools.log',
			'level'   => 'ERROR'
		]);

		// Get PDF-parser.
		$pdfParser = new Parser();

		// Get article folders.
		$articleFolders = $this->getArticleFolders();

		// Get database connection.
		$db = Factory::getDbo();

		// Get required model(s).
		$articleModel = Model::getInstance('article');

		try
		{
			// Define Emergency escape vars.
			$i = 0; $end = 5000; $skipped = []; $dupes = [];

			// Define the root look-up path.
//		    $rootPath = FTKPATH_DRAWINGS;
//			$rootPath = '/www/htdocs/w01b1830/__DRAWINGS_MASTER__/';
			$rootPath = App::getDrawingsPath();
			$dummyPDFPath = FTKPATH_DRAWINGS . '/__DUMMY-DO-NOT-TOUCH__/AAA.BBB.CC.DDDDD.000.0.pdf';
			$dummyPNGPath = FTKPATH_DRAWINGS . '/__DUMMY-DO-NOT-TOUCH__/AAA.BBB.CC.DDDDD.000.0__thumb.png';

			/* carry: Rückgabewert des vorherigen Durchgangs. Im Fall des ersten Durchlaufs enthält dies stattdessen den Wert von initial
			 * item:  Wert des aktuellen Durchgangs
			 */
			array_reduce($articleFolders,
				function ($carry /* the article number or ID, depending on what was fetched */, $item /* the absolut file path */)
				use (&$i, &$end, &$skipped, &$dupes, &$articleModel, &$db, &$dummyPDFPath, &$dummyPNGPath, &$logger, &$pdfParser)
			{
				// Is item a file or folder?
				if (!is_dir($item))
				{
					// Track skipped items.
					$skipped[] = basename($item);

					// Continue with next iteration.
					return false;
				}

				$folder = basename($item);

				// NEW: use with folder name = artID.
				if (!is_numeric($folder))
				{
					// Track skipped items.
					$skipped[] = $folder;

					// Continue with next iteration.
					return false;
				}

				// Fetch article number(s) first.
				$query = $db->getQuery(true)
				->from($db->qn('articles', 'a'))
				->select($db->qn(['artID', 'number']))
				// NEW: use with folder name = article number.
//				->where($db->qn('number') . ' = ' . $db->q($folder));
				// NEW: use with folder name = artID.
				->where($db->qn('artID') . ' = ' . (int) $folder);

				$rs = $db->setQuery($query)->loadObjectList('artID');

				// Skip empty row.
				if (empty($rs))
				{
					// Track skipped items.
					$skipped[] = $folder;

					// Continue with next iteration.
					return false;
				}

				// Sort.
				foreach ($rs as $row)
				{
					// Build path to the drawings root folder.
					$drawingsPathAbs = $item;

					// Drawings root directory existence check.
					$isDirectory = is_dir($drawingsPathAbs);

					// Not found. Skip this article!
					if (!$isDirectory)
					{
						// Track skipped items.
						$skipped[] = $folder;

						continue;
					}

					// Drawings root directory accessibility check.
					$isReadable = is_readable($drawingsPathAbs);
					$isWritable = is_writable($drawingsPathAbs);

					if (!$isReadable)
					{
						throw new RuntimeException(
							sprintf('Operation failed. The drawings root directory <em>%s</em> is not readable.', $row->number)
						);
					}
					if (!$isWritable)
					{
						throw new RuntimeException(
							sprintf('Operation failed. The drawings root directory <em>%s</em> is not writable.', $row->number)
						);
					}

					// Get all associated processes.
					$articleProcesses = $articleModel->getArticleProcesses($row->artID);

					// Create sub-directories and move file(s).
					foreach ($articleProcesses as $procID => $Registry)
					{
						$drawingDataObj = $Registry->extract('drawing');
						$drawingPathRel = $drawingDataObj->get('file');
						$drawingPathAbs = FilesystemHelper::fixPath(FilesystemHelper::absPath($drawingPathRel));

						$sourcePathAbs  = FilesystemHelper::fixPath(pathinfo($drawingPathAbs, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR);
						$sourcePathAbs  = FilesystemHelper::fixPath(pathinfo($sourcePathAbs,  PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR);
						$targetPathAbs  = FilesystemHelper::fixPath(pathinfo($drawingPathAbs, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR);

						$pdfFileName    = basename($drawingPathAbs);
						$fileName       = pathinfo($pdfFileName, PATHINFO_FILENAME);
						$extension      = pathinfo($pdfFileName, PATHINFO_EXTENSION);
//						$globPattern    = FilesystemHelper::fixPath(sprintf('%s/%s.*.%s', $sourcePathAbs, substr($fileName, 0, strrpos($fileName, '.')), $extension));
						$globPattern    = FilesystemHelper::fixPath(sprintf('%s/%s.*',    $sourcePathAbs, substr($fileName, 0, strrpos($fileName, '.'))));
						$glob           = glob($globPattern);

						if (count($glob))
						{
							// If target directory does not exist, create it.
							if (!is_dir($targetPathAbs))
							{
								if (false === mkdir($targetPathAbs, 0755, true))
								{
									throw new Exception(sprintf('Files directory could not be created for article #%d and process #%d.', $row->artID, $procID));
								}
							}

							foreach ($glob as $absGlobbedFilePath)
							{
								$absGlobbedDirPath  = pathinfo($absGlobbedFilePath, PATHINFO_DIRNAME);
								$absGlobbedPDFName = sprintf('%s.%s',
									pathinfo($absGlobbedFilePath, PATHINFO_FILENAME),
									pathinfo($absGlobbedFilePath, PATHINFO_EXTENSION)
								);
								$fromName = $absGlobbedFilePath;
								$toName   = FilesystemHelper::fixPath(sprintf('%s/%s', $targetPathAbs, $absGlobbedPDFName));

								$isDupe = is_file($toName);

								// No dupe. Move.
								if (!$isDupe)
								{
									$moved = rename($fromName, $toName);

									if (!$moved)
									{
										throw new RuntimeException(
											sprintf('Operation failed for article <strong>%s (%d)</strong>. The process drawing <em>%s</em> could not be moved.', $row->number, $row->artID, $fromName)
										);
									}
								}
								else
								{
									$dupes[] = [$fromName, $toName];
								}
							}
						}
					}

					$globPattern = FilesystemHelper::fixPath(sprintf('%s/%s.*', $drawingsPathAbs, substr($row->number, 0, strrpos($row->number, '.'))));
					$glob        = glob($globPattern);

					$glob = array_filter($glob, function($absGlobbedFilePath) use (&$row)
					{
						return strpos($absGlobbedFilePath, $row->number) === false;
					});

					foreach ($glob as $absGlobbedFilePath)
					{
						// Compare PDF with dummy PDF and delete if it is the dummy.
						// Compare Thumb with dummy PDF thumb and delete if it is the dummy.
						if (in_array(mb_strtolower(pathinfo($absGlobbedFilePath, PATHINFO_EXTENSION)), ['pdf','png']))
						{
							$fileIsPDF     = mb_strtolower(pathinfo($absGlobbedFilePath, PATHINFO_EXTENSION)) == 'pdf';
							$fileSizeFile  = filesize($absGlobbedFilePath);
							$fileSizeDummy = mb_strtolower(pathinfo($absGlobbedFilePath, PATHINFO_EXTENSION)) == 'pdf'
								? filesize($dummyPDFPath)
								: (mb_strtolower(pathinfo($absGlobbedFilePath, PATHINFO_EXTENSION)) == 'png'
									? filesize($dummyPNGPath)
									: 0);

							if ($fileSizeFile)
							{
								// File is the DUMMY.
								if ($fileSizeFile === $fileSizeDummy)
								{
									try
									{
										File::delete($absGlobbedFilePath);

										$logger->log('info',  __METHOD__, ['deleted' => $absGlobbedFilePath]);
									}
									catch (Exception $e)
									{
										$logger->log('error',  __METHOD__, ['file' => $absGlobbedFilePath]);
									}
								}
							}
						}
					}
				}

				//-> BEGiN: Emergency escape code
				$i += 1;
				if ($i == $end)
				{
					return false;
				}
				//<- END: Emergency escape code

				return true;
			});
		}
		catch (Exception $e)
		{
			echo '<pre style="color:red"><strong>Exception: </strong>' . print_r($e->getMessage(), true) . '</pre>';
			die;
		}
	}
	private function getArticleFolders() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init return value;
		$dirs = [];

		// @debug
		if (!$this->user->isProgrammer()) :
			return $dirs;
		else :
//			echo '<pre style="color:blue">' . print_r(__METHOD__,  true) . '</pre>';
		endif;

		// Define the root look-up path.
		$rootPath = App::getDrawingsPath();

		// Define the traverse depth.
		$traverseDepth = 0;

		try
		{
			// Create a recursive file system directory iterator.
			$recursiveDirIterator = new RecursiveDirectoryIterator(
				$rootPath,
				FilesystemIterator::SKIP_DOTS/* | FilesystemIterator::FOLLOW_SYMLINKS*/
			);

			foreach ($recursiveDirIterator as $path => $obj)    // $obj will be an SplFileInfo Object with pathName and fileName
			{
				// Skip hidden files/folders.
				if (preg_match('/^\./i', $obj->getFilename())) continue;
				// Skip symlink to drawings directory.
				if (preg_match('/^drawings$/i', $obj->getFilename())) continue;

				// Skip the DUMMY-dir.
				if (mb_strpos($obj->getPathName(), '__DUMMY-DO-NOT-TOUCH__') !== false) continue;   // Skip the dummy drawing

				$dirs[$obj->getFilename()] = $obj->getPathname();
			}

			ksort($dirs);

			/*// Create a recursive iterator.
			$recursiveIterIterator = new RecursiveIteratorIterator(
				$recursiveDirIterator,
				RecursiveIteratorIterator::SELF_FIRST,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			// Set the maximum recursive path.
			$recursiveIterIterator->setMaxDepth($traverseDepth);*/

			/*foreach ($recursiveIterIterator as $path => $obj)    // $obj will be an SplFileInfo Object with pathName and fileName
			{
				// Skip hidden files/folders.
				if (preg_match('/^\./i', $obj->getFilename())) continue;
				// Skip symlink to drawings directory.
				if (preg_match('/^drawings$/i', $obj->getFilename())) continue;

				// Skip the DUMMY-dir.
				if (mb_strpos($obj->getPathName(), '__DUMMY-DO-NOT-TOUCH__') !== false) continue;   // Skip the dummy drawing

				$path .= DIRECTORY_SEPARATOR;

				$dirs[] = $path;
			}*/
		}
		catch (UnexpectedValueException $e)
		{
			// @debug
			echo '<pre>UnexpectedValueException: ' . print_r($e, true) . '</pre>';
			die;
		}
		catch (Exception $e)
		{
			// @debug
			echo '<pre>Exception: ' . print_r($e, true) . '</pre>';
			die;
		}

		return $dirs;
	}
}
