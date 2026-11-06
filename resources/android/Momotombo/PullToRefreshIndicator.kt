package dev.momotombo.plugins.nativephp_pull_to_refresh.ui

import androidx.compose.animation.core.FiniteAnimationSpec
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.snap
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowDownward
import androidx.compose.material.icons.filled.CloudDownload
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.clipPath
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import coil3.request.ImageRequest
import com.nativephp.mobile.bridge.PHPBridge
import java.io.File
import kotlin.math.PI
import kotlin.math.ceil
import kotlin.math.cos
import kotlin.math.max
import kotlin.math.min
import kotlin.math.sin

internal data class PullIndicatorState(
        val preset: String,
        val progressStyle: String,
        val motion: String,
        val progressMode: String,
        val progress: Float,
        val isRefreshing: Boolean,
        val result: String,
        val indicatorSize: Dp,
        val indicatorStrokeWidth: Dp,
        val animationDurationMillis: Int,
        val labels: IndicatorLabels,
        val accentColor: Color,
        val trackColor: Color,
        val successColor: Color,
        val errorColor: Color,
        val reduceMotion: Boolean,
        val androidIconName: String,
        val assetFile: File?,
        val lottieFile: File?,
)

/** Draw the selected preset, platform icon, local asset, or Lottie for the current refresh state. */
@Composable
internal fun PullIndicator(state: PullIndicatorState) = state.IndicatorContent()

@Composable
private fun PullIndicatorState.IndicatorContent() {
    val context = LocalContext.current
    // The gesture always drives semantics; progress-mode only changes what is drawn.
    val gestureProgress = progress.coerceIn(0f, 1f)
    val looping = isIndicatorLooping(result, isRefreshing, progressMode, gestureProgress)
    // Lottie and GIF playback: off for reduced motion and motion="none".
    val animate = !reduceMotion && motion != "none"
    val motionEnabled = animate && animationDurationMillis > 0
    val rawProgress = if (progressMode == "none") 1f else gestureProgress
    val targetProgress =
            when {
                result != "idle" -> 1f
                looping && progressStyle == "wave" -> 0.72f
                looping -> 0.84f
                else -> rawProgress
            }
    val displayedProgress by
            animateFloatAsState(
                    targetValue = targetProgress,
                    animationSpec = motionSpec(motionEnabled, motion, animationDurationMillis, Spring.StiffnessLow),
                    label = "pull-to-refresh-progress",
            )
    val targetScale =
            if ((motion == "spring" || motion == "pulse") && result == "idle" && !reduceMotion) {
                if (isRefreshing) 1.04f else 0.84f + (rawProgress * 0.16f)
            } else {
                1f
            }
    val scale by
            animateFloatAsState(
                    targetValue = targetScale,
                    animationSpec = motionSpec(motionEnabled, motion, animationDurationMillis, Spring.StiffnessMedium),
                    label = "pull-to-refresh-scale",
            )
    // Read only inside graphicsLayer and Canvas blocks, so each loop frame redraws without recomposing.
    val rotation =
            rememberLoopRotation(
                    active = looping && motionEnabled,
                    durationMillis = animationDurationMillis,
                    label = "pull-to-refresh-rotation",
            )
    val breathes = motion == "pulse" && looping && motionEnabled
    val icon = remember(androidIconName) { nativeAndroidIcon(androidIconName) }
    val gifAsset = assetFile?.extension.equals("gif", ignoreCase = true)
    val showAsset = assetFile != null && (!gifAsset || (animate && (isRefreshing || gestureProgress > 0f)))

    Box(
            modifier =
                    Modifier.size(indicatorSize)
                            .graphicsLayer {
                                val breathing = if (breathes) pulseScale(rotation.value, 0.06f) else 1f
                                scaleX = scale * breathing
                                scaleY = scale * breathing
                            }
                            .refreshIndicatorSemantics(labels, gestureProgress, isRefreshing, result),
    ) {
        when {
            result == "success" || result == "error" ->
                    Canvas(Modifier.fillMaxSize()) {
                        val diameter = min(size.width, size.height)
                        drawStatusMark(
                                Offset(size.width / 2f, size.height / 2f),
                                diameter * 0.42f,
                                if (result == "success") successColor else errorColor,
                                result == "error",
                                indicatorStroke(indicatorStrokeWidth, diameter),
                        )
                    }
            lottieFile != null ->
                    IndicatorLottie(
                            file = lottieFile,
                            progress = rawProgress,
                            isRefreshing = isRefreshing,
                            animate = animate,
                            modifier = Modifier.fillMaxSize(),
                    ) {
                        // Static preset drawing until the Lottie file has loaded.
                        Canvas(Modifier.fillMaxSize()) { drawPreset(rawProgress, rotation = 0f, looping = false, state = this@IndicatorContent) }
                    }
            showAsset || icon != null -> {
                if (showAsset) {
                    val model = remember(context, assetFile) { ImageRequest.Builder(context).data(assetFile).build() }
                    AsyncImage(
                            model = model,
                            imageLoader = rememberIndicatorImageLoader(animated = animate),
                            contentDescription = null,
                            contentScale = ContentScale.Fit,
                            modifier = Modifier.align(Alignment.Center).size(indicatorSize * 0.62f),
                    )
                } else if (icon != null) {
                    Icon(
                            imageVector = icon,
                            contentDescription = null,
                            tint = accentColor,
                            modifier =
                                    Modifier.align(Alignment.Center)
                                            .size(indicatorSize * 0.62f)
                                            .graphicsLayer { rotationZ = if (isRefreshing) rotation.value else 0f },
                    )
                }
                Canvas(Modifier.fillMaxSize()) {
                    drawCustomProgressRing(
                            preset = preset,
                            progressStyle = progressStyle,
                            progress = displayedProgress,
                            isRefreshing = looping,
                            rotation = rotation.value,
                            color = accentColor,
                            trackColor = trackColor,
                            strokeWidth = indicatorStrokeWidth,
                    )
                }
            }
            else -> Canvas(Modifier.fillMaxSize()) { drawPreset(displayedProgress, rotation.value, looping, this@IndicatorContent) }
        }
    }
}

/** Draw this state's preset into the full canvas. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawPreset(
        progress: Float,
        rotation: Float,
        looping: Boolean,
        state: PullIndicatorState,
) {
    val diameter = min(size.width, size.height)
    drawFallbackProgress(
            progressStyle = state.progressStyle,
            preset = state.preset,
            center = Offset(size.width / 2f, size.height / 2f),
            radius = diameter * 0.42f,
            progress = progress,
            color = state.accentColor,
            stroke = indicatorStroke(state.indicatorStrokeWidth, diameter),
            trackColor = state.trackColor,
            rotation = rotation,
            looping = looping,
    )
}

/** Stroke width clamped so it stays visible but never swallows a small indicator. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.indicatorStroke(strokeWidth: Dp, diameter: Float): Float =
        min(max(0.5.dp.toPx(), strokeWidth.toPx()), diameter * 0.25f)

/** Draw the shared progress ring used around platform icons and image assets. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawCustomProgressRing(
        preset: String,
        progressStyle: String,
        progress: Float,
        isRefreshing: Boolean,
        rotation: Float,
        color: Color,
        trackColor: Color,
        strokeWidth: Dp,
) {
    val diameter = min(size.width, size.height)
    val center = Offset(size.width / 2f, size.height / 2f)
    val radius = diameter * 0.45f
    val stroke = indicatorStroke(strokeWidth, diameter)
    when (progressStyle) {
        "none" -> return
        "dots" -> {
            drawDotsProgress(center, radius, progress, color, trackColor, stroke, rotation, isRefreshing)
            return
        }
        "arc" -> {
            drawOrbitalProgress(center, radius, progress, color, stroke, trackColor, rotation, isRefreshing, drawCore = false)
            return
        }
    }
    val sweep = if (isRefreshing) 270f else 360f * progress.coerceIn(0f, 1f)
    val start = -90f + if (isRefreshing) rotation else 0f

    if (preset != "minimal") {
        drawCircle(trackColor, radius, center, style = Stroke(width = stroke))
    } else {
        drawArc(
                trackColor,
                0f,
                360f,
                false,
                Offset(center.x - radius, center.y - radius),
                androidx.compose.ui.geometry.Size(radius * 2f, radius * 2f),
                style = Stroke(width = stroke),
        )
    }
    drawArc(
            color,
            start,
            sweep,
            false,
            Offset(center.x - radius, center.y - radius),
            androidx.compose.ui.geometry.Size(radius * 2f, radius * 2f),
            style = Stroke(width = stroke, cap = StrokeCap.Round),
    )
}

/** Resolve the stable Material icon names exposed by the component's public API. */
private fun nativeAndroidIcon(name: String): ImageVector? =
        when (name) {
            "refresh" -> Icons.Filled.Refresh
            "sync" -> Icons.Filled.Sync
            "arrow_downward" -> Icons.Filled.ArrowDownward
            "download" -> Icons.Filled.Download
            "cloud_download" -> Icons.Filled.CloudDownload
            "local_shipping" -> Icons.Filled.LocalShipping
            else -> null
        }

/**
 * Timing for one animated value under the selected motion: bouncy springs for
 * `spring`/`pulse`, eased tweens for `liquid`, and an immediate snap for `spin`
 * (which loops instead) or when motion is disabled.
 */
private fun motionSpec(
        motionEnabled: Boolean,
        motion: String,
        durationMillis: Int,
        springStiffness: Float,
): FiniteAnimationSpec<Float> =
        when {
            !motionEnabled || motion == "spin" -> snap()
            motion == "spring" || motion == "pulse" ->
                    spring(
                            dampingRatio = Spring.DampingRatioMediumBouncy,
                            stiffness = springStiffnessForDuration(springStiffness, durationMillis),
                    )
            else -> tween(durationMillis = durationMillis)
        }

/** Scale spring stiffness around the default duration while keeping extreme values stable. */
private fun springStiffnessForDuration(base: Float, durationMillis: Int): Float {
    if (durationMillis <= 0) return base
    val durationScale = 240f / durationMillis
    return (base * durationScale * durationScale).coerceIn(100f, 1_200f)
}

/** Resolve an existing image or animation only when it remains under the app's public directory. */
internal fun resolveLocalIndicatorFile(context: android.content.Context, path: String): File? {
    if (path.isBlank() ||
                    path.startsWith('/') ||
                    Regex("^[a-zA-Z][a-zA-Z0-9+.-]*:").containsMatchIn(path) ||
                    path.replace('\\', '/').split('/').any { it == ".." || it.isBlank() }
    )
            return null

    return runCatching {
                val publicRoot = File(PHPBridge(context).getLaravelPath(), "public").canonicalFile
                val candidate = File(publicRoot, path.removePrefix("./")).canonicalFile
                if (candidate.path.startsWith(publicRoot.path + File.separator) && candidate.isFile)
                        candidate
                else null
            }
            .getOrNull()
}

/** Draw a clipped liquid fill whose wave height follows normalized pull progress. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawLiquidProgress(
        center: Offset,
        radius: Float,
        progress: Float,
        color: Color,
        stroke: Float,
        trackColor: Color,
        phaseOffset: Float = 0f,
) {
    val circle =
            Path().apply {
                addOval(
                        Rect(
                                center.x - radius,
                                center.y - radius,
                                center.x + radius,
                                center.y + radius
                        )
                )
            }
    val fillHeight = radius * 2f * progress.coerceIn(0f, 1f)
    val waterline = center.y + radius - fillHeight
    val amplitude = min(radius * 0.15f, fillHeight * 0.12f)
    val fill =
            Path().apply {
                moveTo(center.x - radius, center.y + radius)
                lineTo(center.x - radius, waterline)
                val segments = 24
                for (step in 0..segments) {
                    val x = center.x - radius + (radius * 2f * step / segments)
                    val phase =
                            ((x - center.x) / radius * (PI.toFloat() * 1.5f)) +
                                    progress * PI.toFloat() +
                                    phaseOffset * PI.toFloat() / 180f
                    lineTo(x, waterline + sin(phase) * amplitude)
                }
                lineTo(center.x + radius, center.y + radius)
                close()
            }

    clipPath(circle) { drawPath(fill, color) }
    if (fillHeight > radius * 0.18f) {
        drawCircle(
                color.copy(alpha = 0.24f),
                radius * 0.09f,
                Offset(center.x - radius * 0.28f, waterline + radius * 0.28f),
        )
    }
    drawCircle(trackColor, radius, center, style = Stroke(width = stroke))
}

/** Select the fallback drawing from the resolved progress-style, keeping the preset's ring variant. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawFallbackProgress(
        progressStyle: String,
        preset: String,
        center: Offset,
        radius: Float,
        progress: Float,
        color: Color,
        stroke: Float,
        trackColor: Color,
        rotation: Float,
        looping: Boolean,
) {
    when (progressStyle) {
        "wave" -> drawLiquidProgress(center, radius, progress, color, stroke, trackColor, phaseOffset = rotation)
        "arc" -> drawOrbitalProgress(center, radius, progress, color, stroke, trackColor, rotation, looping)
        "dots" -> drawDotsProgress(center, radius, progress, color, trackColor, stroke, rotation, looping)
        "none" ->
                if (looping) drawCircle(color, radius * 0.24f, center)
                else drawPullArrow(center, radius, color, stroke)
        else ->
                if (preset == "minimal") {
                    drawMinimalProgress(center, radius, progress, color, stroke, trackColor, rotation)
                } else {
                    drawSpringProgress(center, radius, progress, color, stroke, trackColor, rotation)
                }
    }
}

/** Draw the orbital arc: a thin track, a progress arc, and a satellite riding its leading edge. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawOrbitalProgress(
        center: Offset,
        radius: Float,
        progress: Float,
        color: Color,
        stroke: Float,
        trackColor: Color,
        rotation: Float,
        looping: Boolean,
        drawCore: Boolean = true,
) {
    drawCircle(trackColor, radius, center, style = Stroke(width = max(0.5.dp.toPx(), stroke * 0.5f)))
    val start = -90f + rotation
    val sweep = if (looping) 110f else 300f * progress.coerceIn(0f, 1f)
    drawArc(
            color = color,
            startAngle = start,
            sweepAngle = sweep,
            useCenter = false,
            topLeft = Offset(center.x - radius, center.y - radius),
            size = androidx.compose.ui.geometry.Size(radius * 2f, radius * 2f),
            style = Stroke(width = stroke, cap = StrokeCap.Round),
    )
    val head = (start + sweep) * PI.toFloat() / 180f
    drawCircle(
            color,
            max(stroke * 1.1f, radius * 0.14f),
            Offset(center.x + cos(head) * radius, center.y + sin(head) * radius),
    )
    if (drawCore) drawCircle(color.copy(alpha = color.alpha * 0.85f), radius * 0.18f, center)
}

/** Draw eight dots that fill with pull progress and chase around the ring while looping. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawDotsProgress(
        center: Offset,
        radius: Float,
        progress: Float,
        color: Color,
        trackColor: Color,
        stroke: Float,
        rotation: Float,
        looping: Boolean,
) {
    val count = 8
    val dotRadius = max(stroke * 0.9f, radius * 0.12f)
    val orbit = radius - dotRadius
    val lit = ceil(progress.coerceIn(0f, 1f) * count).toInt()
    val headIndex = ((rotation / 360f) * count).toInt().mod(count)
    for (index in 0 until count) {
        val angle = (-90f + index * 360f / count) * PI.toFloat() / 180f
        val dotColor =
                if (looping) {
                    val distance = (headIndex - index).mod(count)
                    color.copy(alpha = color.alpha * max(0.2f, 1f - distance * 0.16f))
                } else if (index < lit) {
                    color
                } else {
                    trackColor
                }
        drawCircle(dotColor, dotRadius, Offset(center.x + cos(angle) * orbit, center.y + sin(angle) * orbit))
    }
}

/** Draw the downward arrow shared by the spring ring and the progress-less style. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawPullArrow(
        center: Offset,
        radius: Float,
        color: Color,
        stroke: Float,
) {
    val arrowTop = center.y - radius * 0.28f
    val arrowBottom = center.y + radius * 0.22f
    drawLine(color, Offset(center.x, arrowTop), Offset(center.x, arrowBottom), strokeWidth = stroke, cap = StrokeCap.Round)
    drawLine(
            color,
            Offset(center.x - radius * 0.18f, arrowBottom - radius * 0.18f),
            Offset(center.x, arrowBottom),
            strokeWidth = stroke,
            cap = StrokeCap.Round,
    )
    drawLine(
            color,
            Offset(center.x + radius * 0.18f, arrowBottom - radius * 0.18f),
            Offset(center.x, arrowBottom),
            strokeWidth = stroke,
            cap = StrokeCap.Round,
    )
}

/** Draw the spring preset's circular track and progress arc. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawSpringProgress(
        center: Offset,
        radius: Float,
        progress: Float,
        color: Color,
        stroke: Float,
        trackColor: Color,
        rotation: Float = 0f,
) {
    drawCircle(trackColor, radius, center, style = Stroke(width = stroke))
    drawArc(
            color = color,
            startAngle = -90f + rotation,
            sweepAngle = 360f * progress.coerceIn(0f, 1f),
            useCenter = false,
            topLeft = Offset(center.x - radius, center.y - radius),
            size = androidx.compose.ui.geometry.Size(radius * 2f, radius * 2f),
            style = Stroke(width = stroke, cap = StrokeCap.Round),
    )
    drawPullArrow(center, radius, color, stroke)
}

/** Draw the minimal preset's thin circular track and progress arc. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawMinimalProgress(
        center: Offset,
        radius: Float,
        progress: Float,
        color: Color,
        stroke: Float,
        trackColor: Color,
        rotation: Float = 0f,
) {
    drawArc(
            color = trackColor,
            startAngle = 0f,
            sweepAngle = 360f,
            useCenter = false,
            topLeft = Offset(center.x - radius, center.y - radius),
            size = androidx.compose.ui.geometry.Size(radius * 2f, radius * 2f),
            style = Stroke(width = stroke),
    )
    drawArc(
            color = color,
            startAngle = -90f + rotation,
            sweepAngle = 360f * progress.coerceIn(0f, 1f),
            useCenter = false,
            topLeft = Offset(center.x - radius, center.y - radius),
            size = androidx.compose.ui.geometry.Size(radius * 2f, radius * 2f),
            style = Stroke(width = stroke, cap = StrokeCap.Round),
    )
    drawCircle(color, radius * 0.1f, center)
}

/** Draw the success check or error cross inside the result indicator. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawStatusMark(
        center: Offset,
        radius: Float,
        color: Color,
        isError: Boolean,
        stroke: Float,
) {
    if (isError) {
        drawLine(
                color,
                Offset(center.x - radius * 0.38f, center.y - radius * 0.38f),
                Offset(center.x + radius * 0.38f, center.y + radius * 0.38f),
                strokeWidth = stroke,
                cap = StrokeCap.Round,
        )
        drawLine(
                color,
                Offset(center.x + radius * 0.38f, center.y - radius * 0.38f),
                Offset(center.x - radius * 0.38f, center.y + radius * 0.38f),
                strokeWidth = stroke,
                cap = StrokeCap.Round,
        )
    } else {
        drawLine(
                color,
                Offset(center.x - radius * 0.42f, center.y),
                Offset(center.x - radius * 0.1f, center.y + radius * 0.3f),
                strokeWidth = stroke,
                cap = StrokeCap.Round,
        )
        drawLine(
                color,
                Offset(center.x - radius * 0.1f, center.y + radius * 0.3f),
                Offset(center.x + radius * 0.45f, center.y - radius * 0.34f),
                strokeWidth = stroke,
                cap = StrokeCap.Round,
        )
    }
}
