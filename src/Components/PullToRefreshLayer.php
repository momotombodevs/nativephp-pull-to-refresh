<?php

namespace Momotombo\NativePhpPullToRefresh\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

class PullToRefreshLayer extends NativeBladeComponent
{
    protected bool $isSelfClosing = false;

    protected function elementType(): string
    {
        return 'pull_to_refresh_layer';
    }
}
