<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Model;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo $articleID = isset($_POST['article_id']) ? trim($_POST['article_id']) : '';
    echo $prodID = isset($_POST['process_id']) ? trim($_POST['process_id']) : '';
    echo $file = isset($_POST['wiFile']) ? trim($_POST['wiFile']) : '';

}
