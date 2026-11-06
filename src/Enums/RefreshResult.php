<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Outcome the host publishes after a refresh. */
enum RefreshResult: string
{
    use ParsesInput;

    case Idle = 'idle';
    case Success = 'success';
    case Error = 'error';
}
