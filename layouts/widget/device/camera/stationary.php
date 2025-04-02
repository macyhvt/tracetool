<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang = $this->get('language');
$data = new Registry($this->data);

$tabindex = 0;
?>
<?php /* CSS */
$CSS = <<<STYLES
.custom-select.noSelection {
	background: linear-gradient(#ffeecc,#ffeecc);
    background-color: #ffeecc!important;
}

.form-control.flashing {
	-moz-animation: flash 0.2s;
	-moz-animation-iteration-count: 4;

	-webkit-animation: flash 0.2s;
	-webkit-animation-iteration-count: 4;

	-ms-animation: flash 0.2s;
	-ms-animation-iteration-count: 4;
}

.media-wrapper {
	min-height: 150px;
}

.media-wrapper.hasSnapshot,
.media-wrapper.hasStream {
	box-shadow: 0 0 10px 5px rgba(128, 128, 128, 0.25);
}

*.nemacam-access-denied .alert-danger > .close,
*.nemacam-access-denied .controls,
*.nemacam-access-denied .form-group {
	display: none !important;
}

.hasNemacam.nemacam-incompatible .form-group {
	display: none;
}
.hasNemacam.nemacam-incompatible .alert {
	display: block!important;
}
.hasNemacam.nemacam-initialising .media-wrapper,
.hasNemacam.nemacam-initialising #media-container,
.hasNemacam.nemacam-pending .media-wrapper,
.hasNemacam.nemacam-pending #media-container {
	background-image: url('/assets/img/global/busy-blue.gif');
	background-size: 80%;
	background-position-x: 50%;
	background-position-y: 50%;
	background-repeat: no-repeat;
}
.hasNemacam.nemacam-connecting #media-container,
.hasNemacam.nemacam-loading    #media-container,
.hasNemacam.nemacam-connecting #video-wrapper:not(.hasStream),
.hasNemacam.nemacam-loading    #video-wrapper:not(.hasStream),
.hasNemacam.nemacam-connecting #image-wrapper:not(.hasSnapshot),
.hasNemacam.nemacam-loading    #image-wrapper:not(.hasSnapshot) {
	background-image: url('/assets/ico/ajax-loader-large.gif');
	background-size: 20%;
	background-position-x: 50%;
	background-position-y: 50%;
	background-repeat: no-repeat;
}
.hasNemacam .media-wrapper .media-controls {
	z-index: 1;
}

.hasNemacam.nemacam-loading label[for="devices"] + div {
	background-image: url('/assets/ico/ajax-loader.gif');
	background-position: 15px 50%;
	background-repeat: no-repeat;
}

.hasNemacam.nemacam-streaming video {
	background-color: #000;
}

@-webkit-keyframes flash {
      0% { border-color: none }
     50% { border-color: #80bdff; outline: 0; box-shadow: 0 0 1rem 0.2rem rgb(255,165,0) }
    100% { border-color: none }
}

@-moz-keyframes flash {
      0% { border-color: none }
     50% { border-color: #80bdff; outline: 0; box-shadow: 0 0 1rem 0.2rem rgb(255,165,0) }
    100% { border-color: none }
}

@-ms-keyframes flash {
      0% { border-color: none }
     50% { border-color: #80bdff; outline: 0; box-shadow: 0 0 1rem 0.2rem rgb(255,165,0) }
    100% { border-color: none }
}

.initializing-indicator {
	display: none;
}
.hasNemacam.nemacam-initialising .busy-indicator {
	position: fixed;
	top: 0;
	left: 0;
	bottom: 0;
	right: 0;
	z-index: 999;
	background-color: #F5F5F5;
	display: block;
}

.three-balls {
	margin: 0 auto;
	width: 70px;
	text-align: center;
	position: absolute;
	left: 0;
	right: 0;
	top: 45%;
}

.three-balls .ball {
	position: relative;
	width: 15px;
	height: 15px;
	border-radius: 50%;
	display: inline-block;
	-webkit-animation: bouncedelay 2.0s infinite cubic-bezier(.62, .28, .23, .99) both;
	animation: bouncedelay 2.0s infinite cubic-bezier(.62, .28, .23, .99) both;
}

.three-balls .ball1 {
	-webkit-animation-delay: -.16s;
	animation-delay: -.16s;
}

.three-balls .ball2 {
	-webkit-animation-delay: -.08s;
	animation-delay: -.08s;
}

@keyframes bouncedelay {
	0% {
		bottom: 0;
		background-color: #03A9F4;
	}

	16.66% {
		bottom: 40px;
		background-color: #FB6542;
	}

	33.33% {
		bottom: 0px;
		background-color: #FB6542;
	}

	50% {
		bottom: 40px;
		background-color: #FFBB00;
	}

	66.66% {
		bottom: 0px;
		background-color: #FFBB00;
	}

	83.33% {
		bottom: 40px;
		background-color: #03A9F4;
	}

	100% {
		bottom: 0;
		background-color: #03A9F4;
	}
}

@-webkit-keyframes bouncedelay {
	0% {
		bottom: 0;
		background-color: #03A9F4;
	}

	16.66% {
		bottom: 40px;
		background-color: #FB6542;
	}

	33.33% {
		bottom: 0px;
		background-color: #FB6542;
	}

	50% {
		bottom: 40px;
		background-color: #FFBB00;
	}

	66.66% {
		bottom: 0px;
		background-color: #FFBB00;
	}

	83.33% {
		bottom: 40px;
		background-color: #03A9F4;
	}

	100% {
		bottom: 0;
		background-color: #03A9F4;
	}
}
STYLES;

if (class_exists('WyriHaximus\CssCompress\Factory')) :
	$compressor = \WyriHaximus\CssCompress\Factory::constructSmallest();
	$CSS = $compressor->compress($CSS);
endif;
?>
<style><?php echo $CSS; ?></style>

<?php /* CAMERA/VIDEO device list */
$parentContainer = sprintf('#p-%s', hash('CRC32', $data->get('procID')));
$processID = $data->get('procID');

++$tabindex;

$HTML = <<<HTML
<aside data-bind="nemacam" data-parent-container="$parentContainer" data-process="$processID">
	<div class="form-group row" data-bind="checkIsDeviceSelected">
		<label for="devices" data-for="camera" class="col-md-3 col-lg-2 col-form-label">%STR1%:</label>
		<div class="col-md-9 col-lg-10">
			<select id="devices"
					data-id="camera"
					name="devices"
					data-name="camera"
					class="form-control custom-select devices"
					aria-describedby="selectCameraHelp"
					data-require="localStorage"
					data-bind="populate:populateList, select:deviceSelection"
					tabindex="$tabindex"
					disabled readonly
			>
				<option value="">%STR2%</option>
			</select>
			<small id="selectCameraHelp" class="form-text text-muted visually-hidden sr-only"><strong class="text-uppercase mr-3">%STR3% :</strong>%STR4%</small>
		</div>
	</div>

	<div class="media-container" id="media-container">
		<div class="form-group row position-relative media-wrapper mx-0 mb-0" id="video-wrapper">
			<div class="btn-group-vertical controls media-controls stream-controls position-absolute border-dark dropdown-menu-right mt-2 mr-md-3 mr-lg-2 disabled d-none"
				 role="group"
				 aria-label="Camera controls"
			>
				<button type="button"
						class="btn btn-info"
						id="btnSnapper"
						title="%STR5%"
						data-toggle="tooltip"
						disabled
				>
					<i class="fas fa-camera"></i>
					<span class="visually-hidden sr-only text-uppercase d-md-inline ml-lg-1">%STR6%</span>
				</button>
			</div>

			<video id="video" class="video position-absolute w-100 h-100 m-0 p-0"></video>

			<span class="btn btn-primary position-absolute media-counter mt-2 ml-md-3 ml-lg-2 d-none">%STR7%
				<span class="badge badge-light count ml-2"></span>
				<span class="sr-only">%STR8%</span>
			</span>
		</div>

		<div class="form-group row position-relative media-wrapper mx-0 mb-0" id="image-wrapper" style="display:none">
			<div class="btn-group-vertical controls media-controls photo-controls position-absolute border-dark dropdown-menu-right mt-2 mr-md-3 mr-lg-2 disabled d-none"
				 role="group"
				 aria-label="Picture controls"
			>
				<button type="button"
						class="btn btn-info"
						id="btnAdd"
						form="imageForm"
						title="%STR9%"
						data-toggle="tooltip"
						disabled
				>
					<i class="fas fa-plus"></i>
					<span class="visually-hidden sr-only text-uppercase d-md-inline ml-lg-1">%STR10%</span>
				</button>

				<button type="button"
						class="btn btn-danger"
						id="btnEraser"
						form="imageForm"
						title="%STR11%"
						data-toggle="tooltip"
						disabled
				>
					<i class="fas fa-trash-alt"></i>
					<span class="visually-hidden sr-only text-uppercase ml-2">%STR11%</span>
				</button>
			</div>

			<form name="imageForm" id="imageForm">
				<div id="former-image-wrapper" class="former-media-wrapper position-relative">
					<img src="" class="image w-100 h-100 invisible" id="image" alt="" width="" height="">
				</div>
			</form>
		</div>
	</div>
</aside>
HTML;
$HTML = preg_replace(
	[
		'/%STR1%/',
		'/%STR2%/',
		'/%STR3%/',
		'/%STR4%/',
		'/%STR5%/',
		'/%STR6%/',
		'/%STR7%/',
		'/%STR8%/',
		'/%STR9%/',
		'/%STR10%/',
		'/%STR11%/',
		'/%STR12%/'
	],
	[
		Text::translate('COM_FTK_LABEL_DEVICES_TEXT', $lang),
		Text::translate('COM_FTK_LIST_OPTION_DETECTING_DEVICES_TEXT', $lang),
		Text::translate('COM_FTK_LABEL_HELP_TEXT', $lang),
		Text::translate('COM_FTK_HINT_FIX_CAMERA_ACCESS_TEXT', $lang),
		Text::translate('COM_FTK_LINK_TITLE_TAKE_PICTURES_TEXT', $lang),
		Text::translate('COM_FTK_BUTTON_TEXT_SNAP_TEXT', $lang),
		Text::translate('COM_FTK_DATE_CURRENTLY_TEXT', $lang),
		Text::translate('COM_FTK_LABEL_UNSAVED_PHOTOGRAPHS_TEXT', $lang),
		Text::translate('COM_FTK_BUTTON_TITLE_ACCEPT_PHOTOGRAPH_TEXT', $lang),
		Text::translate('COM_FTK_BUTTON_TEXT_ADD_TEXT', $lang),
		Text::translate('COM_FTK_BUTTON_TITLE_DISCARD_PHOTOGRAPH_TEXT', $lang),
		Text::translate('COM_FTK_BUTTON_TEXT_DISCARD_TEXT', $lang),
	],
	$HTML
);

if (class_exists('WyriHaximus\HtmlCompress\Factory')) :
	$compressor = \WyriHaximus\HtmlCompress\Factory::constructSmallest();
	$HTML = $compressor->compress($HTML);
endif;

echo $HTML;
?>

<?php /* Javascript was outsourced from former widget "camera.php" into separate JS-project called "Nemacam" */
$JS = file_get_contents(FTKPATH_ASSETS . DIRECTORY_SEPARATOR . '/js/vendor/froetek/nemacam/dist/nemacam.min.js');

/*// Do not compress if the source file is the *.min.js file.
if (class_exists('WyriHaximus\JsCompress\Factory')) :
	$compressor = \WyriHaximus\JsCompress\Factory::construct();
	$JS = $compressor->compress($JS);
endif;*/
?>
<script><?php echo $JS; ?></script>
