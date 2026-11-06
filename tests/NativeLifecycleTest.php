<?php

/**
 * Compiles each platform's refresh lifecycle together with its test driver and
 * runs it. The lifecycle files are pure Kotlin / Swift with no UI dependencies,
 * so they build with a plain compiler instead of a full Android or Xcode build.
 *
 * Kotlin needs a JDK plus a Kotlin compiler in the Gradle cache (any Android
 * build leaves one there). Swift needs Xcode's toolchain. Missing toolchains
 * skip instead of failing, so the PHP suite still runs on CI without them.
 */
$pluginPath = dirname(__DIR__);

/**
 * Run a command and return [exit code, combined output].
 *
 * @param  list<string>  $command
 * @return array{0: int, 1: string}
 */
function runNativeCommand(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($process), (string) $output];
}

function nativeScratchDirectory(): string
{
    $directory = sys_get_temp_dir().'/ptr-native-'.bin2hex(random_bytes(6));
    mkdir($directory);

    return $directory;
}

/**
 * Locate the newest cached Kotlin compiler and the jars it needs at runtime.
 *
 * @return array{compiler: string, stdlib: string}|null
 */
function kotlinToolchain(): ?array
{
    $cache = (getenv('GRADLE_USER_HOME') ?: getenv('HOME').'/.gradle').'/caches/modules-2/files-2.1';
    $find = fn (string $pattern): array => glob("{$cache}/{$pattern}") ?: [];
    $newest = function (string $pattern) use ($find): ?string {
        $jars = array_filter($find($pattern), fn (string $jar): bool => ! str_contains($jar, '-sources'));
        usort($jars, fn (string $a, string $b): int => version_compare(basename(dirname($a, 2)), basename(dirname($b, 2))));

        return $jars === [] ? null : end($jars);
    };

    $compiler = $newest('org.jetbrains.kotlin/kotlin-compiler-embeddable/*/*/*.jar');
    if ($compiler === null) {
        return null;
    }

    $version = basename(dirname($compiler, 2));
    $sameVersion = fn (string $artifact): ?string => $newest("org.jetbrains.kotlin/{$artifact}/{$version}/*/{$artifact}-{$version}.jar");
    $jars = [
        $compiler,
        $stdlib = $sameVersion('kotlin-stdlib'),
        $sameVersion('kotlin-script-runtime'),
        $sameVersion('kotlin-daemon-embeddable'),
        $sameVersion('kotlin-reflect'),
        $newest('org.jetbrains.intellij.deps/trove4j/*/*/*.jar'),
        $newest('org.jetbrains.kotlinx/kotlinx-coroutines-core-jvm/*/*/*.jar'),
        $newest('org.jetbrains/annotations/*/*/*.jar'),
    ];

    if (in_array(null, $jars, true) || runNativeCommand(['java', '-version'])[0] !== 0) {
        return null;
    }

    return ['compiler' => implode(PATH_SEPARATOR, $jars), 'stdlib' => $stdlib];
}

it('runs the Android refresh lifecycle checks', function () use ($pluginPath) {
    $toolchain = kotlinToolchain();
    if ($toolchain === null) {
        $this->markTestSkipped('No JDK or cached Kotlin compiler found.');
    }

    $classes = nativeScratchDirectory();
    [$compiled, $compileOutput] = runNativeCommand([
        'java', '-cp', $toolchain['compiler'], 'org.jetbrains.kotlin.cli.jvm.K2JVMCompiler',
        '-no-reflect', '-no-stdlib', '-cp', $toolchain['stdlib'], '-d', $classes,
        "{$pluginPath}/resources/android/Momotombo/PullToRefreshLifecycle.kt",
        "{$pluginPath}/tests/Native/PullToRefreshLifecycleTest.kt",
    ]);
    expect($compiled)->toBe(0, $compileOutput);

    [$exitCode, $output] = runNativeCommand([
        'java', '-cp', $classes.PATH_SEPARATOR.$toolchain['stdlib'],
        'dev.momotombo.plugins.nativephp_pull_to_refresh.ui.PullToRefreshLifecycleTestKt',
    ]);
    expect($exitCode)->toBe(0, $output);
    expect($output)->toContain('OK Kotlin lifecycle');
})->group('native');

it('runs the iOS refresh lifecycle checks', function () use ($pluginPath) {
    if (PHP_OS_FAMILY !== 'Darwin' || runNativeCommand(['xcrun', '--find', 'swiftc'])[0] !== 0) {
        $this->markTestSkipped('Swift lifecycle checks need the Xcode toolchain.');
    }

    // Top-level test code is only allowed in a file named main.swift.
    $build = nativeScratchDirectory();
    copy("{$pluginPath}/tests/Native/PullToRefreshLifecycleTests.swift", "{$build}/main.swift");

    [$compiled, $compileOutput] = runNativeCommand([
        'xcrun', 'swiftc', "{$pluginPath}/resources/ios/PullToRefreshLifecycle.swift", "{$build}/main.swift", '-o', "{$build}/lifecycle",
    ]);
    expect($compiled)->toBe(0, $compileOutput);

    [$exitCode, $output] = runNativeCommand(["{$build}/lifecycle"]);
    expect($exitCode)->toBe(0, $output);
    expect($output)->toContain('OK Swift lifecycle');
})->group('native');
