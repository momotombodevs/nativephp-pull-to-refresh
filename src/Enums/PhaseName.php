<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Lifecycle phase a composed indicator can declare; `pulling` is the fallback for omitted phases. */
enum PhaseName: string
{
    use ParsesInput;

    case Pulling = 'pulling';
    case Release = 'release';
    case Refreshing = 'refreshing';
    case Success = 'success';
    case Error = 'error';
}
