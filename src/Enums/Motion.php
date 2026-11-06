<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Non-essential animation treatment; `none` and the platform's reduced-motion setting stop every loop. */
enum Motion: string
{
    use ParsesInput;

    case None = 'none';
    case Spring = 'spring';
    case Liquid = 'liquid';
    case Pulse = 'pulse';
    case Spin = 'spin';
}
