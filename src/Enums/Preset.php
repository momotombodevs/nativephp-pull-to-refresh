<?php

namespace Momotombo\NativePhpPullToRefresh\Enums;

/** Named indicator treatment; each preset supplies a default progress style and motion. */
enum Preset: string
{
    use ParsesInput;

    case Liquid = 'liquid';
    case Spring = 'spring';
    case Minimal = 'minimal';
    case Orbital = 'orbital';
    case Wave = 'wave';
    case Pulse = 'pulse';

    /** Progress drawing used unless the author sets `progress-style` explicitly. */
    public function defaultProgressStyle(): ProgressStyle
    {
        return match ($this) {
            self::Spring, self::Minimal => ProgressStyle::Ring,
            self::Liquid, self::Wave => ProgressStyle::Wave,
            self::Orbital => ProgressStyle::Arc,
            self::Pulse => ProgressStyle::Dots,
        };
    }

    /** Motion used unless the author sets `motion` explicitly. */
    public function defaultMotion(): Motion
    {
        return match ($this) {
            self::Spring => Motion::Spring,
            self::Liquid, self::Wave => Motion::Liquid,
            self::Minimal, self::Orbital => Motion::Spin,
            self::Pulse => Motion::Pulse,
        };
    }
}
