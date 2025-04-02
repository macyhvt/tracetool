<?php
// Register required libraries.
use Nematrack\Helper\LayoutHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

echo LayoutHelper::render(sprintf('system.element.drawingnumber.%s', $this->get('layout', 'default')), $this->data, ['language' => $this->get('language')]);
