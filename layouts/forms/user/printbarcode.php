<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View;
use Logincodegen\Barcode;
/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');
$userID = $user->get('userID');
$orgID  = $user->get('orgID');

$list   = [];
$filter = $input->getString('filter', (string) ListModel::FILTER_ALL);
$layout = $input->getCmd('layout');
$task   = $input->post->getCmd('task')  ?? ($input->getCmd('task') ?? null);
$oid    = $input->getInt('oid');


$useRoleGet = $model->getActiveUserRole($userID);

?>
<?php
$request_uri = $_SERVER['REQUEST_URI'];

// Parse the URL to extract the query string
$query_string = parse_url($request_uri, PHP_URL_QUERY);

// Parse the query string to extract parameters
parse_str($query_string, $query_params);

// Now you can access specific parameters
$email = $query_params['email'] ?? '';
$password = $query_params['pass'] ?? '';
$organisation = $query_params['org'] ?? '';
$name = $query_params['name'] ?? '';

//echo $urlss= __DIR__ . '/barcode.php';
$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]";
?>
   <!-- <p><img src="<?php /*echo $actual_link;echo UriHelper::osSafe(UriHelper::fixURL(sprintf('barcode.php'))); */?>?s=dmtxr&wq=4&d=<?php /*echo $email; */?>"></p>
    <p><img src="<?php /*echo $actual_link;echo UriHelper::osSafe(UriHelper::fixURL(sprintf('barcode.php'))); */?>?s=dmtxr&wq=4&d=<?php /*echo $name;*/?>"></p>-->

<?php /* Access check */
$formData = null;

?>
    <style>

        .empLoginName li{list-style: none;display: inline-block;}
        .empLoginName p {margin: 5px 0;}

        .frontlogincard {border: 1px solid;padding: 0 15px !important;box-sizing: border-box;background: #fff;height: 250px;}
        .frontlogincard .logoDiv {text-align: left;padding-top: 10px;}
        .frontlogincard .logoDiv2 {text-align: right;padding-top: 10px;}
        .logoDiv img{width: 35%;}
        .logoDiv2 img{width: 40%;}
        .logoDiv3 img, .logoDiv4 img{width: 39%;}
        .logoDiv3 img {margin-top: 72px;}
        .logoDiv4 img {margin-top: 45px;}
        .ph2 {font-size: 17px;}
        .orgnamep i{margin-right: 5px;}
        .orgnamep .fa-map-marker:before {font-size: 14px;}
        .orgnamep span{font-weight: 500;font-size: 14px;}
        .roleNamev {margin: 0 5px 0 0;font-size: 13px;}
        .frontlogincard .logoDiv3 {text-align: left;padding-top: 10px;}
        .frontlogincard .logoDiv4 {text-align: right;padding-top: 10px;}
        .logoDiv3 img, .logoDiv4 img{width: 44%;}
        .frontlogincard .empLoginName {text-align: center;}
        .backlogincard {border: 1px solid;padding: 0 15px !important;box-sizing: border-box;background: #fff;height: 250px;}
        .backlogincard .userpasslabel{text-align: center;padding: 84px 0 0 0;margin: 0;}

        .fa-map-marker:before {font-size: 12px;}
        .rowssbtm {margin-top: -142px;}
        .empColor {padding: 22px 0 0 15px;}
        .empColor li {list-style: none;display: inline-block;margin-right: 5px;}
        .colorUserSpan{width: 8px;height: 70px;position: relative;display: inline-block;}
        .colorUserRole{width: 12px;height: 12px;position: relative;display: inline-block;margin-right: 3px;}
        /* Media query for print */
        @media print {
            .frontlogincard{ width: 48%; float: left; margin-right: 2%; }
            .rowss { display: flex; }
            .logss { width: 50%; display: inline-block; margin-right: -4px; }
            .logoDiv, .logoDiv2 { display: block; margin: 0 auto; }
            .logoDiv img, .logoDiv2 img{ width: 40%; }

            .rowssbtm { display: flex; margin-top: -60px; }
            .logssbtm { width: 50%; display: inline-block; margin-right: -4px; }
            .logoDiv3, .logoDiv4 { display: block; margin: 0 auto; }
            .logoDiv3 img, .logoDiv4 img{ width: 40%; }

            .backlogincard { width: 48%; float: left; margin-right: 2%; }
            .printingAssets span, .printingAssets button, footer, header { display: none; }

            .ph2 {font-size: 18px;}
            .empColor {margin-bottom: -80px;}
            .logoDiv3 img { margin-top: -10px; }
            .logoDiv4 img {margin-top: -25px;}
        }
    </style>
    <div class="container" id="printArea">

        <div class="col-md-6 frontlogincard">
            <div class="row rowss">
                <div class="col-md-6 logss logoDiv"><img src="https://nematrack.com/assets/img/global/logos/froetek-logo.png"></div>
                <div class="col-md-6 logss logoDiv2">
                    <img src="https://mike.nematrack.com/assets/img/global/logos/Nematech-logoJPG">
                </div>
            </div>
            <div class="row">
                <?php
                $roleColors = [
                    'COM_FTK_GROUP_ADMINISTRATOR' => '#BB0000',
                    'COM_FTK_GROUP_PROGRAMMER' => '#BB0000',
                    'COM_FTK_GROUP_WORKER' => '#0023CF',
                    'COM_FTK_GROUP_QUALITY_MANAGER' => '#E0EA00',
                    'COM_FTK_GROUP_QUALITY_ASSURANCE' => '#00A936',
                    'COM_FTK_GROUP_MANAGER' => '#7D00A9',
                ];
                $roleLabels = [
                    'COM_FTK_GROUP_ADMINISTRATOR' => 'Admin',
                    'COM_FTK_GROUP_PROGRAMMER' => 'Coder',
                    'COM_FTK_GROUP_WORKER' => 'Worker',
                    'COM_FTK_GROUP_QUALITY_MANAGER' => 'QM',
                    'COM_FTK_GROUP_QUALITY_ASSURANCE' => 'QA',
                    'COM_FTK_GROUP_MANAGER' => 'Manager',
                ];
                $output = '';
                $outColor = '';
                foreach ($useRoleGet as $roleData) {
                    $role = $roleData['name'];
                    if (array_key_exists($role, $roleColors)) {
                        $color = $roleColors[$role];
                        $label = $roleLabels[$role] ?? $role;
                        $outColor .= "<li><span class='colorUserSpan' style='background:$color'></span></li>";
                        $output .= "<li><span class='colorUserRole' style='background:$color'></span><span class='roleNamev'>$label</span></li>";
                    }
                }
                $output = rtrim($output, ', ');
                ?>
                <div class="col-md-2 empColor">
                    <?php echo $outColor; ?>
                </div>
                <div class="col-md-8 empLoginName">
                    <h2 class="ph2"><?php echo $name; ?></h2>
                    <p class="orgnamep"><i class="fas fa-map-marker"></i><span><?php echo $organisation; ?></span></p>
                    <?php
                    echo $output;
                    ?>
                </div>
                <div class="col-md-2 empEmpty"></div>
            </div>
            <div class="row">
                <div class="col-md-12 empLoginName">
                    <img src="<?php echo $actual_link;echo UriHelper::osSafe(UriHelper::fixURL(sprintf('barcode.php'))); ?>?s=dmtxr&wq=4&d=<?php echo $email; ?>">
                </div>
            </div>
            <div class="row rowssbtm">
                <div class="col-md-6 logssbtm logoDiv3">
                    <img src="https://mike.nematrack.com/assets/img/global/logos/nemectek.png">
                </div>
                <div class="col-md-6 logssbtm logoDiv4">
                    <img src="https://mike.nematrack.com/assets/img/global/logos/nematrack.png">
                </div>
            </div>
        </div>

        <div class="col-md-6 backlogincard">
            <p class="userpasslabel">
                <img style="width: 40%;" src="<?php echo $actual_link;echo UriHelper::osSafe(UriHelper::fixURL(sprintf('barcode.php'))); ?>?s=dmtxr&wq=4&d=<?php echo $password; ?>">
            </p>
        </div>
    </div>

    <div class="printingAssets">
        <button style="margin: 10px 13px;" class="btn btn-info btn-save" id="printButtons" onclick="printContent()">Print</button>
        <span>Or press CTRL + P </span>
    </div>
    <script>
        function printContent() {
            window.print();
        }
    </script>
<?php // Free memory
unset($input);
unset($item);
unset($list);
unset($model);
unset($projects);
unset($user);
unset($view);
