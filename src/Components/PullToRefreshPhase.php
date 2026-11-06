<?php

namespace Momotombo\NativePhpPullToRefresh\Components;

use Native\Mobile\Edge\Components\Native\NativeBladeComponent;

/** Blade `<native:pull-to-refresh-phase>`: what the composed indicator draws in one lifecycle phase. */
class PullToRefreshPhase extends NativeBladeComponent
{
    protected bool $isSelfClosing = false;

    /** Return the EDGE node type declared by the plugin manifest. */
    protected function elementType(): string
    {
        return 'pull_to_refresh_phase';
    }
}
