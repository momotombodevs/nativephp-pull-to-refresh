<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Role that decides how a composed layer reacts to progress and motion. */
enum LayerRole: string
{
    use ParsesInput;

    case Background = 'background';
    case Track = 'track';
    case Progress = 'progress';
    case Artwork = 'artwork';
    case Label = 'label';
    case Status = 'status';
}
