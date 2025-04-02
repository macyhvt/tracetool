<?php


/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$menu   = $this->get('menu');
$user   = $view->get('user');
$userID = null;
$orgID  = null;

?>

<nav class="navbar navbar-expand-lg main" id="navbar-main">

	<?php // TODO - implement language key toggle depending on whether this menu is shown or hidden ?>
	<?php if ($menu) : ?>
	<button type="button"
			class="navbar-toggler float-right"
			title="<?php echo Text::translate('COM_FTK_LINK_SHOW_NAVIGATION_LABEL', $lang); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_LINK_SHOW_NAVIGATION_LABEL', $lang); ?>"
			aria-controls="mainNavigation"
			aria-expanded="false"
			data-toggle="collapse"
			data-target="#mainNavigation"
	>
		<span class="navbar-toggler-icon"></span>
	</button>

	<h4 class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_MAIN_MENU_TEXT', $lang); ?></h4>

	<div class="navbar-collapse collapse" id="mainNavigation">
		<?php /*   M A I N   M E N U   */ ?>
		<ul class="navbar-nav pt-3 pt-md-0 mr-auto">
			<?php /*   P A R T S   */ ?>
			<li class="nav-item px-sm-2" id="nav-item-parts">
				<a href="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?>"
				   class="nav-link<?php echo $view->get('name') === 'parts' ? ' active' : ''; ?>"
				   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_PARTS_DESC', $lang); ?>"
				><?php echo Text::translate('COM_FTK_MENU_ITEM_PARTS_LABEL', $lang); ?></a>
			</li>

			<?php /*   M A S T E R   D A T A   */ ?>
			<?php if (is_object($user) && !$user->isGuest()) : ?>
			<li class="nav-item dropdown px-sm-2" id="nav-item-masterdata">
				<a href="javascript:void(0)"
				   class="nav-link dropdown-toggle"
				   id="navbarDropdownMenu-masterdata"
				   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_MASTERDATA_DESC', $lang); ?>"
				   aria-label="<?php echo Text::translate('COM_FTK_MENU_ITEM_MASTERDATA_DESC', $lang); ?>"
				   data-toggle="dropdown"
				   aria-haspopup="true"
				   aria-expanded="false"
				   role="button"
				><?php
					echo Text::translate('COM_FTK_MENU_ITEM_MASTERDATA_LABEL', $lang);
				?></a>
				<ul class="nav-sub dropdown-menu border-0 pt-0 pb-2 jumbotron-fluid" aria-labelledby="navbarDropdownMenu-masterdata">
					<?php // All non-guest users ?>
					<?php if (is_object($user) && $user->getFlags() >= User::ROLE_WORKER) : ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-masterdata-articles"><?php // link to articles ?>
						<a href="<?php echo View::getInstance('articles', ['language' => $lang])->getRoute(); ?>"
						   class="dropdown-item<?php echo $view->get('name') === 'articles' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_ARTICLES_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_ARTICLES_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>

					<?php /* M A N U F A C T U R E R S    only + privileged users only */ ?>
					<?php /* E Q U I P M E N T    only + privileged users only */ ?>

					<?php //  +  only + high privileged users only ?>
					<?php if (FALSE && $orgID == '1' && is_object($user) && $user->getFlags() >= User::ROLE_PROGRAMMER) : // NO LONGER accessable ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-masterdata-users"><?php // link to users ?>
						<a href="<?php echo View::getInstance('users', ['language' => $lang])->getRoute(); ?>"
						   class="dropdown-item<?php echo $view->get('name') === 'users' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_USERS_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_USERS_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>

					<?php // All non-guest users (customers/suppliers see link to their organisation) ?>
					<?php if (is_object($user) && $user->getFlags() >= User::ROLE_WORKER) : ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-masterdata-organisations"><?php // link to organisation(s) + user(s) ?>
					<?php // Low-privileged users must only see their own organisation ?>
					<?php 	if (is_object($user) && $user->getFlags() < User::ROLE_PROGRAMMER) : ?>
						<a href="<?php echo sprintf('%s&oid=%d',
								View::getInstance('organisation', ['language' => $lang])->getRoute(),
								$user->get('orgID')
						   ); ?>"
						   class="dropdown-item<?php echo $view->get('name') === 'organisations' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_ORGANISATION_%s_MASTERDATA_TEXT', (is_object($user) && $user->getFlags() >= User::ROLE_MANAGER ? 'manage' : 'view'))), $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_ORGANISATION_LABEL',  $lang); ?></a>
					<?php // Programmers and high-privileged users can see everything ?>
					<?php 	else : ?>
						<a href="<?php echo View::getInstance('organisations', ['language' => $lang])->getRoute(); ?>"
						   class="dropdown-item<?php echo $view->get('name') === 'organisations' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_ORGANISATIONS_AND_USERS_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_ORGANISATIONS_AND_USERS_LABEL', $lang); ?></a>
					<?php 	endif; ?>
					</li>
					<?php endif; ?>

					<?php  only + high privileged users only ?>
					<?php if (is_object($user) && $user->getFlags() >= User::ROLE_WORKER && UserHelper::isFroetekOrNematechMember($user)) : // Management is granted to privileged - and -users only ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-masterdata-processes"><?php // link to processes ?>
						<a href="<?php echo View::getInstance('processes', ['language' => $lang])->getRoute(); ?>"
						   class="dropdown-item<?php //echo $view->get('name') === 'processes' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_PROCESSES_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_PROCESSES_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>
                    <?php //  +  only + high privileged users only ?>
                    <?php if (is_object($user) && $user->getFlags() >= User::ROLE_WORKER && UserHelper::isFroetekOrNematechMember($user)) : ?>
                        <li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-masterdata-maingroups"><?php // link to processes ?>
                            <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=maingroups&layout=list', $lang ))); ?>"
                               class="dropdown-item<?php //echo $view->get('name') === 'processes' ? ' active' : ''; ?>"
                               title="<?php //echo Text::translate('COM_FTK_MENU_ITEM_PROCESSES_DESC', $lang); ?>"
                            ><?php echo "Main Groups"; ?></a>
                        </li>
                    <?php endif; ?>

					<?php // All non-guest users ?>
					<?php if (is_object($user) && $user->getFlags() >= User::ROLE_WORKER) : ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-masterdata-projects"><?php // link to projects ?>
						<a href="<?php echo View::getInstance('projects', ['language' => $lang])->getRoute(); ?>"
						   class="dropdown-item<?php echo $view->get('name') === 'projects' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_PROJECTS_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_PROJECTS_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>
				</ul>
			</li>
			<?php endif; ?>

			<?php /*   A D M I N I S T R A T I O N   */ ?>
			<?php //  + high privileged users only ?>
			<?php if (is_object($user) && $user->getFlags() >= User::ROLE_MANAGER && in_array($orgID, [1])) :	// Management is granted to privileged -users only ?>
			<li class="nav-item dropdown px-sm-2" id="nav-item-administration">
				<a href="javascript:void(0)"
				   class="nav-link dropdown-toggle"
				   id="navbarDropdownMenu-administration"
				   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_ADMINISTRATION_DESC', $lang); ?>"
				   aria-label="<?php echo Text::translate('COM_FTK_MENU_ITEM_ADMINISTRATION_DESC', $lang); ?>"
				   data-toggle="dropdown"
				   aria-haspopup="true"
				   aria-expanded="false"
				   role="button"
				><?php echo Text::translate('COM_FTK_MENU_ITEM_ADMINISTRATION_LABEL', $lang); ?></a>
				<ul class="nav-sub dropdown-menu border-0 pt-0 pb-2 jumbotron-fluid" aria-labelledby="navbarDropdownMenu-administration">
					<?php //  only + high privileged users only ?>
					<?php if (is_object($user) && $user->getFlags() >= User::ROLE_QUALITY_ASSURANCE) : // Reporting back is granted to high privileged -users only ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-administration-parts-unbooked">
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=parts&layout=unbooked', $lang ))); ?>"
						   class="dropdown-item<?php echo ($view->get('name') === 'parts' && $this->get('name') === 'unbooked') ? ' active' : ''; ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_BOOK_PARTS_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>
				</ul>
			</li>
			<?php endif; ?>

			<?php /*   S T A T I S T I C S   */ ?>
			<?php //if (is_object($user) && !$user->isCustomer() && $user->getFlags() >= User::ROLE_WORKER) : ?>
			<?php if (is_object($user) && !$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
			<li class="nav-item dropdown px-sm-2" id="nav-item-statistics">
				<a href="javascript:void(0)"
				   class="nav-link dropdown-toggle"
				   id="navbarDropdownMenu-statistics"
				   data-toggle="dropdown"
				   aria-haspopup="true"
				   aria-expanded="false"
				   role="button"
				><?php echo Text::translate('COM_FTK_MENU_ITEM_STATISTICS_LABEL', $lang); ?></a>
				<ul class="nav-sub dropdown-menu border-0 pt-0 pb-2 jumbotron-fluid" aria-labelledby="navbarDropdownMenu-statistics">
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-statistics-projects"><?php // link to project throughput statistics ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=statistics&layout=article.matrix', $lang ))); // link to project throughput ?>"
						   class="dropdown-item<?php echo $view->get('layout') === 'article.matrix' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_LABEL_STATISTICS_PROJECT_THROUGHPUT_TEXT', $lang); ?>"
						><?php echo Text::translate('COM_FTK_LABEL_STATISTICS_PROJECT_THROUGHPUT_TEXT', $lang); ?></a>
					</li>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-statistics-processes"><?php // link to process throughput statistics ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=statistics&layout=tracking.processes', $lang ))); // link to process throughput ?>"
						   class="dropdown-item<?php echo $view->get('layout') === 'tracking.processes' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_LABEL_STATISTICS_PROCESS_OUTPUT_TEXT', $lang); ?>"
						><?php echo Text::translate('COM_FTK_LABEL_STATISTICS_PROCESS_OUTPUT_TEXT', $lang); ?></a>
					</li>
					<?php if (FALSE && is_object($user) && $user->getFlags() >= User::ROLE_ADMINISTRATOR && UserHelper::isFroetekOrNematechMember($user)) : // NOT YET accessible ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-statistics-errors"><?php // link to process errors stats ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=statistics&layout=errors.summary', $lang ))); // link to process errors ?>"
						   class="dropdown-item<?php echo $view->get('layout') === 'errors.summary' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_LABEL_STATISTICS_PROCESS_ERRORS_TEXT', $lang); ?>"
						><?php echo Text::translate('COM_FTK_LABEL_STATISTICS_PROCESS_ERRORS_TEXT', $lang); ?></a>
					</li>
					<?php endif; ?>
					<?php if (FALSE && is_object($user) && $user->getFlags() >= User::ROLE_PROGRAMMER) : ?>
					<li class="nav-item nav-sub-item<?php /* dropdown-item */ ?> text-left py-2 small" id="nav-sub-item-statistics-output-monitor"><?php // link to CSV data generator ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=statistics&layout=project.monitoring', $lang ))); // link to project monitor export tool (CSV-generator) ?>"
						   class="dropdown-item<?php echo $view->get('layout') === 'output.monitor' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_PROJECTS_MONITORING_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_PROJECTS_MONITORING_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>
				</ul>
			</li>
			<?php endif; ?>

			<?php /*   T O O L S   */ ?>
			<?php if (is_object($user) && $user->isProgrammer()) : // These tools must be avaiable to developers only, because these are here to support their job ?>
			<li class="nav-item dropdown px-sm-2" id='nav-item-tools'>
				<a href="javascript:void(0)"
				   class="nav-link dropdown-toggle"
				   id="navbarDropdownMenu-tools"
				   data-toggle="dropdown"
				   aria-haspopup="true"
				   aria-expanded="false"
				   role="button"
				><?php echo Text::translate('COM_FTK_MENU_ITEM_TOOLS_LABEL', $lang); ?></a>
				<ul class="nav-sub dropdown-menu border-0 pt-0 pb-2 jumbotron-fluid" aria-labelledby="navbarDropdownMenu-tools">
					<?php // Drawings mover ?>
					<?php if (is_object($user) && $user->isProgrammer()) : // link to PDF sorter to sort article- and process drawings into numerical folders (IDs) - OBSOLETE, because task is finished ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-tools-drawings-mover"><?php // link to tool article-/process-drawings mover ?>
						<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=tools&layout=sort.drawings', $lang))); ?>"
						   class="dropdown-item<?php echo $this->view->get('layout') === 'sort.drawings' ? ' active' : ''; ?>"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_SORT_DRAWINGS_DESC', $lang); ?>"
						><?php echo Text::translate('COM_FTK_MENU_ITEM_SORT_DRAWINGS_LABEL', $lang); ?></a>
					</li>
					<?php endif; ?>

					<?php // Single Image-uploader ?>
					<?php if (FALSE && is_object($user) && $user->isProgrammer()) : // link to single image-uploader - DiSABLED ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-tools-batch-loader"><?php // link to tool single picture processor ?>
						<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=tools&layout=take.picture', $lang))); ?>"
						   class="dropdown-item<?php echo $this->view->get('layout') === 'take.picture' ? ' active' : ''; ?>"
						   title=""
						><?php echo Text::translate('COM_FTK_LINK_TITLE_TAKE_PICTURE_TEXT', $lang); ?></a>
					</li>
					<?php endif; ?>

					<?php // Batch Image-upload ?>
					<?php if (is_object($user) && $user->isProgrammer()) : // link to batch image uploader ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-tools-batch-upload"><?php // link to tool batch image upload ?>
						<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=tools&layout=batch.upload.images', $lang))); ?>"
						   class="dropdown-item<?php echo $this->view->get('layout') === 'batch.upload.images' ? ' active' : ''; ?>"
						   title=""
						><?php echo Text::translate('COM_FTK_LABEL_TOOL_BATCH_LOADER_PICTURES_TEXT', $lang); ?></a>
					</li>

					<?php // horizontal ruler ?>
					<li class="d-none d-md-flex dropdown-divider"></li>
					<?php endif; ?>

					<?php // Barcode generator ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-tools-batch-upload"><?php // link to tool batch image upload ?>
						<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('/lib/vendor/froetek/code-generator/test/barcoder.php', $lang))); ?>"
						   class="dropdown-item"
						   title="<?php echo Text::translate('COM_FTK_LABEL_TOOL_BARCODE_GENERATOR_TEXT', $lang); ?>"
						   target="_blank"
						><?php echo Text::translate('COM_FTK_LABEL_TOOL_BARCODE_GENERATOR_TEXT', $lang); ?></a>
					</li>
					<?php // Tracking code generator and -validator ?>
					<li class="nav-item nav-sub-item text-left py-2 small" id="nav-sub-item-tools-batch-upload"><?php // link to tool batch image upload ?>
						<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('/lib/vendor/froetek/code-generator/test/coder.php', $lang))); ?>"
						   class="dropdown-item"
						   title="<?php echo Text::translate('COM_FTK_LABEL_TOOL_TRACKING_CODE_GENERATOR_AND_VALIDATOR_TEXT', $lang); ?>"
						   target="_blank"
						><?php echo Text::translate('COM_FTK_LABEL_TOOL_TRACKING_CODE_GENERATOR_AND_VALIDATOR_TEXT', $lang); ?></a>
					</li>
				</ul>
			</li>
			<?php endif; ?>

			<?php /*   H E L P   */ ?>
			<li class="nav-item px-sm-2" id="nav-item-help">
				<a href="<?php echo View::getInstance('help', ['language' => $lang])->getRoute(); ?>"
				   class="nav-link<?php echo $view->get('name') === 'help' ? ' active' : ''; ?>"
				><?php echo Text::translate('COM_FTK_LABEL_HELP_TEXT', $lang); ?></a>
			</li>
		</ul>
        <style>.nwfture{
                position: absolute;
                right: 0;
                color: red;
                font-weight: 500;
                font-size: 10px;
                animation: zoom-in-zoom-out 1s linear infinite;
            }
            .nwfture2{
                position: absolute;
                left: 27px;
                color: green;
                font-weight: 500;
                font-size: 10px;
                animation: zoom-in-zoom-out 1s linear infinite;
            }
            @keyframes zoom-in-zoom-out {
                0% {
                    transform: scale(1, 1);
                }
                50% {
                    transform: scale(1.3, 1.3);
                }
                100% {
                    transform: scale(1, 1);
                }
            }</style>
		<?php /*   U S E R   S E L F   M A N A G E M E N T   */ ?>
		<?php if ($user->get('userID')) : ?>
		<ul class="navbar-nav pt-md-0 ml-auto">
			<li class="nav-item px-sm-2 dropdown" id="nav-item-profile">
				<a href="javascript:void(0)"
				   class="nav-link"
				   id="navbarDropdownMenu-user"
				   data-toggle="dropdown"
				   aria-haspopup="true"
				   aria-expanded="false"
				   role="button"
				>
					<i class="far fa-user"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_MENU_ITEM_USER_ACCOUNT_LABEL', $lang); ?></span>
				</a>
				<ul class="nav-sub dropdown-menu text-left border-0 pt-0 pb-2" aria-labelledby="navbarDropdownMenu-user" style="left:auto; right:0">
					<li class="nav-item nav-sub-item dropdown-item text-right px-2 py-1 small" id="nav-sub-item-user-name" style="padding-bottom:0.15rem!important;
					text-decoration:none!important">
						<span class="nav-link disabled">
							<strong><?php echo html_entity_decode($user->get('fullname')); ?></strong>
						</span>
					</li>
					<li class="d-none d-md-flex dropdown-divider"></li>
					<li class="nav-item nav-sub-item dropdown-item text-right px-2 py-1 small" id="nav-sub-item-user-account" style="padding-bottom:0.15rem!important"><?php // link to user account ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=user&layout=profile', $lang ))); ?>"
						   class="nav-link<?php echo $view->get('name') === 'user' ? ' active' : ''; ?> py-0"
						   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_USER_PROFILE_MANAGE_LABEL', $lang); ?>"
						><?php echo Text::translate('COM_FTK_LABEL_USER_PROFILE_LABEL', $lang); ?></a>
					</li>
                    <li class="d-none d-md-flex dropdown-divider"></li>
                    <li class="nav-item nav-sub-item dropdown-item text-right px-2 py-1 small" id="nav-sub-item-user-account" style="padding-bottom:0.15rem!important">
                        <?php // link to user account ?> <span class="nwfture2">New</span>
                        <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=user&layout=preference', $lang ))); ?>"
                           class="nav-link<?php echo $view->get('name') === 'user' ? ' ' : ''; ?> py-0"
                           title="<?php echo Text::translate('COM_FTK_LABEL_USERSETTING_TEXT', $lang); ?>"
                        ><i class="fas fa-cog ml-2" style="margin-right:0.1rem!important"></i><?php echo Text::translate('COM_FTK_LABEL_USERSETTING_TEXT', $lang); ?></a>
                    </li>
					<li class="d-none d-md-flex dropdown-divider"></li>
					<li class="nav-item nav-sub-item dropdown-item text-right px-2 py-1 small" id="nav-sub-item-user-logout">
						<?php $redirect = ArrayHelper::getValue($_SERVER, 'PHP_SELF', 'index.php', 'STRING') . '?hl=' . $lang; ?>
						<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s', $lang ))); ?>"
							  method="post"
							  name="logoutUserForm"
							  class="nav-link form-signout m-0 px-0 py-1"
							  data-submit=""
						>
							<input type="hidden" name="view"   value="user" />
							<input type="hidden" name="task"   value="logout" />
							<input type="hidden" name="se"     value="0" />		<?php // '0' will silently redirect user to login screen, '1' will render system message telling the user the logout happened due to session timeout ?>
							<?php if (0) : // Disabled on 2020-12-31 after Session management refactoring ?>
							<input type="hidden" name="uid"    value="<?php echo $userID; ?>" />
							<input type="hidden" name="return" value="<?php echo base64_encode($redirect); ?>" />
							<?php endif; ?>
							<button type="submit" class="btn btn-block btn-link btn-submit nav-link text-right py-0 pr-md-1">
								<span class="small"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_LOGOUT_TEXT', $lang); ?></span>
								<i class="fas fa-sign-out-alt ml-2" style="margin-right:0.1rem!important"></i>
							</button>
						</form>
					</li>
				</ul>
			</li>
		</ul>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</nav>
