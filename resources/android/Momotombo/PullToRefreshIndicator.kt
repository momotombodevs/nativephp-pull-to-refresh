package dev.momotombo.plugins.nativephp_pull_to_refresh.ui

import androidx.compose.animation.core.AnimationSpec
import androidx.compose.animation.core.FiniteAnimationSpec
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
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
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.ProgressBarRangeInfo
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.progressBarRangeInfo
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.ImageLoader
import coil3.compose.AsyncImage
import coil3.request.ImageRequest
import com.airbnb.lottie.compose.LottieAnimation
import com.airbnb.lottie.compose.LottieCompositionSpec
import com.airbnb.lottie.compose.LottieConstants
import com.airbnb.lottie.compose.animateLottieCompositionAsState
import com.airbnb.lottie.compose.rememberLottieComposition
import com.nativephp.mobile.bridge.PHPBridge
import java.io.File
import kotlin.math.PI
import kotlin.math.max
import kotlin.math.min
import kotlin.math.sin

internal data class PullIndicatorState(
        val preset: String,
        val progress: Float,
        val isRefreshing: Boolean,
        val result: String,
        val indicatorSize: Dp,
        val indicatorStrokeWidth: Dp,
        val animationDurationMillis: Int,
        val pullLabel: String,
        val releaseLabel: String,
        val refreshingLabel: String,
        val successLabel: String,
        val errorLabel: String,
        val accentColor: Color,
        val trackColor: Color,
        val successColor: Color,
        val errorColor: Color,
        val reduceMotion: Boolean,
        val androidIconName: String,
        val assetFile: File?,
        val lottieFile: File?,
        val imageLoader: ImageLoader,
)

/** Draw the selected preset, platform icon, local asset, or Lottie for the current refresh state. */
@Composable
internal fun PullIndicator(state: PullIndicatorState) {
    val preset = state.preset
    val progress = state.progress
    val isRefreshing = state.isRefreshing
    val result = state.result
    val indicatorSize = state.indicatorSize
    val indicatorStrokeWidth = state.indicatorStrokeWidth
    val animationDurationMillis = state.animationDurationMillis
    val pullLabel = state.pullLabel
    val releaseLabel = state.releaseLabel
    val refreshingLabel = state.refreshingLabel
    val successLabel = state.successLabel
    val errorLabel = state.errorLabel
    val accentColor = state.accentColor
    val trackColor = state.trackColor
    val successColor = state.successColor
    val errorColor = state.errorColor
    val reduceMotion = state.reduceMotion
    val androidIconName = state.androidIconName
    val assetFile = state.assetFile
    val lottieFile = state.lottieFile
    val imageLoader = state.imageLoader
    val context = LocalContext.current
    val rawProgress = progress.coerceIn(0f, 1f)
    val targetProgress =
            when {
                result != "idle" -> 1f
                isRefreshing && preset == "liquid" -> 0.72f
                isRefreshing -> 0.84f
                else -> rawProgress
            }
    val progressSpec: FiniteAnimationSpec<Float> =
            when {
                reduceMotion || animationDurationMillis == 0 -> snap()
                preset == "spring" ->
                        spring(
                                dampingRatio = Spring.DampingRatioMediumBouncy,
                                stiffness = springStiffnessForDuration(Spring.StiffnessLow, animationDurationMillis),
                        )
                preset == "liquid" -> tween(durationMillis = animationDurationMillis)
                else -> snap()
            }
    val displayedProgress by
            animateFloatAsState(
                    targetValue = targetProgress,
                    animationSpec = progressSpec,
                    label = "pull-to-refresh-progress",
            )
    val targetScale =
            if (preset == "spring" && result == "idle") {
                if (isRefreshing) 1.04f else 0.84f + (rawProgress * 0.16f)
            } else {
                1f
            }
    val scaleSpec: FiniteAnimationSpec<Float> =
            when {
                reduceMotion || animationDurationMillis == 0 -> snap()
                preset == "spring" ->
                        spring(
                                dampingRatio = Spring.DampingRatioMediumBouncy,
                                stiffness = springStiffnessForDuration(Spring.StiffnessMedium, animationDurationMillis),
                        )
                preset == "liquid" -> tween(durationMillis = animationDurationMillis)
                else -> snap()
            }
    val scale by
            animateFloatAsState(
                    targetValue = targetScale,
                    animationSpec = scaleSpec,
                    label = "pull-to-refresh-scale",
            )
    val rotationSpec: AnimationSpec<Float> =
            if (isRefreshing && !reduceMotion && animationDurationMillis > 0) {
                infiniteRepeatable(
                        tween(durationMillis = animationDurationMillis, easing = LinearEasing),
                        RepeatMode.Restart
                )
            } else {
                snap()
            }
    val rotation by
            animateFloatAsState(
                    targetValue = if (isRefreshing && !reduceMotion) 360f else 0f,
                    animationSpec = rotationSpec,
                    label = "pull-to-refresh-rotation",
            )
    val icon = remember(androidIconName) { nativeAndroidIcon(androidIconName) }
    val gifAsset = assetFile?.extension.equals("gif", ignoreCase = true)
    val showAsset =
            assetFile != null &&
                    (!gifAsset || (!reduceMotion && (isRefreshing || rawProgress > 0f)))
    val description =
            when {
                result == "success" -> successLabel
                result == "error" -> errorLabel
                isRefreshing -> refreshingLabel
                rawProgress >= 1f -> releaseLabel
                else -> pullLabel
            }

    Box(
            modifier =
                    Modifier.size(indicatorSize)
                            .graphicsLayer {
                                scaleX = scale
                                scaleY = scale
                            }
                            .semantics {
                                contentDescription = description
                                stateDescription =
                                        when {
                                            result == "success" -> "Complete"
                                            result == "error" -> "Failed"
                                            isRefreshing -> "In progress"
                                            rawProgress >= 1f -> "Ready"
                                            rawProgress > 0f -> "Pulling"
                                            else -> "Idle"
                                        }
                                if (isRefreshing || result != "idle")
                                        liveRegion = LiveRegionMode.Polite
                                if (result == "idle") {
                                    progressBarRangeInfo =
                                            if (isRefreshing) {
                                                ProgressBarRangeInfo.Indeterminate
                                            } else {
                                                ProgressBarRangeInfo(rawProgress, 0f..1f)
                                            }
                                }
                            },
    ) {
        when (result) {
            "success", "error" ->
                    Canvas(Modifier.fillMaxSize()) {
                        val diameter = min(size.width, size.height)
                        val center = Offset(size.width / 2f, size.height / 2f)
                        val radius = diameter * 0.42f
                        val stroke = min(max(0.5.dp.toPx(), indicatorStrokeWidth.toPx()), diameter * 0.25f)
                        drawStatusMark(
                                center,
                                radius,
                                if (result == "success") successColor else errorColor,
                                result == "error",
                                stroke
                        )
                    }
            else ->
                    when {
                        lottieFile != null ->
                                PullLottieIndicator(
                                        file = lottieFile,
                                        preset = preset,
                                        progress = rawProgress,
                                        isRefreshing = isRefreshing,
                                        reduceMotion = reduceMotion,
                                        accentColor = accentColor,
                                        trackColor = trackColor,
                                        indicatorStrokeWidth = indicatorStrokeWidth,
                                        modifier = Modifier.fillMaxSize(),
                                )
                        showAsset -> {
                            val model =
                                    remember(context, assetFile) {
                                        ImageRequest.Builder(context).data(assetFile).build()
                                    }
                            AsyncImage(
                                    model = model,
                                    imageLoader = imageLoader,
                                    contentDescription = null,
                                    contentScale = ContentScale.Fit,
                                    modifier =
                                            Modifier.align(Alignment.Center)
                                                    .size(indicatorSize * 0.62f),
                            )
                            Canvas(Modifier.fillMaxSize()) {
                                drawCustomProgressRing(
                                        preset = preset,
                                        progress = displayedProgress,
                                        isRefreshing = isRefreshing,
                                        rotation = rotation,
                                        color = accentColor,
                                        trackColor = trackColor,
                                        strokeWidth = indicatorStrokeWidth.toPx(),
                                )
                            }
                        }
                        icon != null -> {
                            Icon(
                                    imageVector = icon,
                                    contentDescription = null,
                                    tint = accentColor,
                                    modifier =
                                            Modifier.align(Alignment.Center)
                                                    .size(indicatorSize * 0.62f)
                                                    .graphicsLayer {
                                                        rotationZ =
                                                                if (isRefreshing && !reduceMotion)
                                                                        rotation
                                                                else 0f
                                                    },
                            )
                            Canvas(Modifier.fillMaxSize()) {
                                drawCustomProgressRing(
                                        preset = preset,
                                        progress = displayedProgress,
                                        isRefreshing = isRefreshing,
                                        rotation = rotation,
                                        color = accentColor,
                                        trackColor = trackColor,
                                        strokeWidth = indicatorStrokeWidth.toPx(),
                                )
                            }
                        }
                        else ->
                                Canvas(Modifier.fillMaxSize()) {
                                    val diameter = min(size.width, size.height)
                                    val center = Offset(size.width / 2f, size.height / 2f)
                                    val radius = diameter * 0.42f
                                    val stroke =
                                            min(
                                                    max(0.5.dp.toPx(), indicatorStrokeWidth.toPx()),
                                                    diameter * 0.25f,
                                            )

                                    when (preset) {
                                        "liquid" ->
                                                drawLiquidProgress(
                                                        center,
                                                        radius,
                                                        displayedProgress,
                                                        accentColor,
                                                        stroke,
                                                        trackColor
                                                )
                                        "minimal" ->
                                                drawMinimalProgress(
                                                        center = center,
                                                        radius = radius,
                                                        progress = displayedProgress,
                                                        color = accentColor,
                                                        stroke = stroke,
                                                        trackColor = trackColor,
                                                        rotation = rotation,
                                                )
                                        else ->
                                                drawSpringProgress(
                                                        center,
                                                        radius,
                                                        displayedProgress,
                                                        accentColor,
                                                        stroke,
                                                        trackColor,
                                                        rotation
                                                )
                                    }
                                }
                    }
        }
    }
}

/** Scrub Lottie progress while pulling, then loop the animation while loading when motion is allowed. */
@Composable
private fun PullLottieIndicator(
        file: File,
        preset: String,
        progress: Float,
        isRefreshing: Boolean,
        reduceMotion: Boolean,
        accentColor: Color,
        trackColor: Color,
        indicatorStrokeWidth: Dp,
        modifier: Modifier,
) {
    val composition by rememberLottieComposition(LottieCompositionSpec.File(file.absolutePath))
    val animatedProgress by
            animateLottieCompositionAsState(
                    composition = composition,
                    isPlaying = isRefreshing && !reduceMotion,
                    iterations = LottieConstants.IterateForever,
            )

    Box(modifier) {
        if (composition != null) {
            LottieAnimation(
                    composition = composition,
                    progress = {
                        when {
                            isRefreshing && reduceMotion -> 1f
                            isRefreshing -> animatedProgress
                            else -> progress.coerceIn(0f, 1f)
                        }
                    },
                    modifier = Modifier.fillMaxSize(),
            )
        } else {
            Canvas(Modifier.fillMaxSize()) {
                val diameter = min(size.width, size.height)
                val center = Offset(size.width / 2f, size.height / 2f)
                val radius = diameter * 0.42f
                val stroke = min(max(0.5.dp.toPx(), indicatorStrokeWidth.toPx()), diameter * 0.25f)
                when (preset) {
                    "liquid" -> drawLiquidProgress(center, radius, progress, accentColor, stroke, trackColor)
                    "minimal" -> drawMinimalProgress(center, radius, progress, accentColor, stroke, trackColor = trackColor)
                    else -> drawSpringProgress(center, radius, progress, accentColor, stroke, trackColor = trackColor)
                }
            }
        }
    }
}

/** Draw the shared progress ring used around platform icons and image assets. */
private fun androidx.compose.ui.graphics.drawscope.DrawScope.drawCustomProgressRing(
        preset: String,
        progress: Float,
        isRefreshing: Boolean,
        rotation: Float,
        color: Color,
        trackColor: Color,
        strokeWidth: Float,
) {
    val diameter = min(size.width, size.height)
    val center = Offset(size.width / 2f, size.height / 2f)
    val radius = diameter * 0.45f
    val stroke = min(max(0.5.dp.toPx(), strokeWidth), diameter * 0.25f)
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
                                    progress * PI.toFloat()
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
    val arrowTop = center.y - radius * 0.28f
    val arrowBottom = center.y + radius * 0.22f
    drawLine(
            color,
            Offset(center.x, arrowTop),
            Offset(center.x, arrowBottom),
            strokeWidth = stroke,
            cap = StrokeCap.Round,
    )
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
