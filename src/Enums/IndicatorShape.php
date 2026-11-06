<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Silhouette of the indicator container. */
enum IndicatorShape: string
{
    use ParsesInput;

    case Circle = 'circle';
    case Capsule = 'capsule';
    case Rounded = 'rounded';
    case Blob = 'blob';
}
