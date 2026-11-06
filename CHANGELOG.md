# Changelog

All notable changes to `momotombo/nativephp-pull-to-refresh` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-11-06

### Added

- Declarative `pull_to_refresh_indicator`, `pull_to_refresh_phase`, and `pull_to_refresh_layer` EDGE nodes for composing visual indicators from Blade or PHP.
- Phase-specific compositions for `pulling`, `release`, `refreshing`, `success`, and `error`, with `pulling` fallback behavior.
- Visual layer roles for background, track, progress, artwork, labels, and status content.
- Typed PHP builders for appearance, indicators, phases, and layers.
- Presets `orbital`, `wave`, and `pulse`; shapes, progress styles, motion modes, and progress modes.
- String-backed enums (`Preset`, `IndicatorShape`, `ProgressStyle`, `Motion`, `ProgressMode`, `RefreshResult`, `AndroidIndicatorIcon`, `PhaseName`, `LayerRole`) accepted anywhere the matching string value is.
- Native refresh-lifecycle checks for Kotlin and Swift in `tests/Native`, compiled and run by the Pest suite when the toolchains are available.
- Native drawings for the `arc` (orbital satellite) and `dots` progress styles, and a glyph-only `none` style, on iOS and Android.
- `progress-mode="indeterminate"` loops the indicator as soon as the pull starts.
- Each preset resolves default `progress-style` and `motion` values; explicit values and non-empty Blade bindings win.

### Changed

- Native renderers now separate indicator sentinels from scrollable children while retaining the v1 native indicator as the fallback.
- Package manifest version is now 1.1.0.
- Animation timing follows `motion` instead of the preset name; `motion="none"` and reduced motion disable loops on both platforms.
- Composed indicators inherit `motion` and `progress-mode` from the container and use a real loop for spinning and pulsing layers.
- Android indicator boxes clip and shadow with the selected `shape` instead of always using a circle.
- Accessibility progress now always follows the gesture, including with `progress-mode="none"`.
- iOS draws the `wave` style as a real liquid fill with a travelling wave, matching Android.
- `rounded` and `blob` radii scale with the container on both platforms, and `capsule` widens the container so it no longer renders as a circle.
- Enum validation errors share one format that names the kebab-case attribute and lists the allowed values, for example `shape must be circle, capsule, rounded, or blob`.

### Fixed

- A refresh whose callback changes nothing (no new rows, the same result as last time) no longer leaves the indicator spinning forever. NativePHP drops identical frames, so the element now serializes a `render_sequence` that advances on every render, and both renderers treat a new sequence as the callback's acknowledgement.

### Migration

- Existing v1 Blade and PHP APIs remain supported. No migration is needed unless adopting composed indicators.
- A custom indicator may contain visual EDGE components only; nested scroll containers, pull-to-refresh elements, and interactive callbacks are rejected.

## [1.0.0] - 2026-10-07

### Added

- Native pull-to-refresh EDGE container for NativePHP Mobile 4.5+.
- Android renderer backed by Material 3 pull-to-refresh and Jetpack Compose.
- iOS renderer backed by UIKit `UIRefreshControl` and SwiftUI content.
- `spring`, `liquid`, and `minimal` indicator presets.
- App-controlled `refreshing`, `result`, and `result-duration` state props.
- Registered `on-refresh` callback support for Blade and fluent PHP elements.
- Configurable colors, dimensions, animation duration, platform icons, accessibility labels, PNG/SVG/GIF assets, and Lottie JSON assets.
- Local asset path validation and mutually exclusive visual-source validation.
- Reduced-motion handling and VoiceOver/TalkBack refresh actions.
- Composer package metadata, NativePHP plugin manifest, package tests, Boost guidelines, and release documentation.

### Validation

- Composer metadata validated with `composer validate --strict`.
- Package test suite passing with 13 tests and 191 assertions.
- NativePHP plugin manifest validated without errors; the validator may report the expected informational warning that this visual-only plugin defines no `bridge_functions`.

[Unreleased]: https://github.com/momotombodevs/nativephp-pull-to-refresh/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/momotombodevs/nativephp-pull-to-refresh/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/momotombodevs/nativephp-pull-to-refresh/releases/tag/v1.0.0
