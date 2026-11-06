<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Material icons the Android renderer can draw inside the indicator. */
enum AndroidIndicatorIcon: string
{
    use ParsesInput;

    case Refresh = 'refresh';
    case Sync = 'sync';
    case ArrowDownward = 'arrow_downward';
    case Download = 'download';
    case CloudDownload = 'cloud_download';
    case LocalShipping = 'local_shipping';
}
