# Changelog

All notable changes to `momotombo/nativephp-pull-to-refresh` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Continue documenting native acceptance results as they are verified on Android and iOS.

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

[Unreleased]: https://github.com/momotombodevs/nativephp-pull-to-refresh/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/momotombodevs/nativephp-pull-to-refresh/releases/tag/v1.0.0
