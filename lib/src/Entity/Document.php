<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Nematrack\Entity;
use Nematrack\Helper\FilesystemHelper;

/**
 * Class description
 */
class Document extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  2.10.1
	 */
	protected $docID = null;

	/**
	 * @var    string  The related content section e.g. 'Help'
	 * @since  2.10.1
	 */
	protected $section = null;

	/**
	 * @var    string  The related context e.g. 'Article', 'Equipment', etc.
	 * @since  2.10.1
	 */
	protected $context = null;

	/**
	 * @var    string  The related topic.
	 * @since  2.10.1
	 */
	protected $topic = null;

	/**
	 * @var    bool  Flag indicating whether the document relates to mobile usage only.
	 * @since  2.10.1
	 */
	protected $mobile = null;

	/**
	 * @var    string  A MD5-hash of the file name to circumvent char encoding in URIs.
	 * @since  2.10.1
	 */
	protected $hash = null;

	/**
	 * @var    string  The actual file name.
	 * @since  2.10.1
	 */
	protected $name = null;

	/**
	 * @var    string  The file extension.
	 * @since  2.10.1
	 */
	protected $ext = null;

	/**
	 * @var    float  The file version.
	 * @since  2.10.1
	 */
	protected $version = null;

	/**
	 * @var    integer  The language id.
	 * @since  1.1
	 */
	protected $lngID = null;

	/**
	 * @var    DateTime  The row creation date and time.
	 * @since  1.1
	 */
	protected $created = null;

	/**
	 * @var    string  The name of the creator of this row.
	 * @since  1.1
	 */
	protected $created_by = null;

	/**
	 * @var    DateTime  Date and time when this row was last edited.
	 * @since  1.1
	 */
	protected $modified = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $modified_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is blocked.
	 * @since  1.1
	 */
	protected $blocked = null;

	/**
	 * @var    DateTime  Date and time when this row was blocked.
	 * @since  1.1
	 */
	protected $blockDate = null;

	/**
	 * @var    string  The name of the blocker of this row.
	 * @since  1.1
	 */
	protected $blocked_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as archived.
	 * @since  1.1
	 */
	protected $archived = null;

	/**
	 * @var    DateTime  Date and time when this row was archived.
	 * @since  1.1
	 */
	protected $archiveDate = null;

	/**
	 * @var    string  The name of the archivator of this row.
	 * @since  1.1
	 */
	protected $archived_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is marked as deleted.
	 * @since  1.4
	 */
	protected $trashed = null;

	/**
	 * @var    DateTime  Date and time when this row was trashed.
	 * @since  1.1
	 */
	protected $trashDate = null;

	/**
	 * @var    string  The name of the trasher of this row.
	 * @since  1.1
	 */
	protected $trashed_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as deleted.
	 * @since  1.1
	 */
	protected $deleted = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $deleted_by = null;


	/** Properties that are no database table columns */

	protected $path = null;

	/**
	 * {@inheritdoc}
	 * @see Entity::__construct
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::getTableName
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return 'documents';
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::bind
	 */
	public function bind(array $data = []) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		// Populate path and existence flag properties.
		$this->path = sprintf('%s/help/%s/%s',
			FilesystemHelper::absPath(FilesystemHelper::relPath(FTKPATH_DOWNLOADS)),
			$this->get('language'),
			ltrim(
				sprintf('%s.%s',
					$this->get('hash', ''),
					$this->get('ext',  '')
				),
				'.' // If both hash and ext are empty then there's only the '.' that has to be deleted
			)
		);

		$this->path = (is_file($this->path) && is_readable($this->path)) ? sprintf('%s?v=%s', $this->path, $this->get('version', '')) : null;

		return $this;
	}
}
