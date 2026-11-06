package dev.momotombo.plugins.nativephp_pull_to_refresh.ui

import android.os.Build
import androidx.compose.animation.core.AnimationSpec
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.snap
import androidx.compose.animation.core.tween
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.ImageLoader
import coil3.compose.AsyncImage
import coil3.gif.AnimatedImageDecoder
import coil3.gif.GifDecoder
import coil3.svg.SvgDecoder
import com.airbnb.lottie.compose.LottieAnimation
import com.airbnb.lottie.compose.LottieCompositionSpec
import com.airbnb.lottie.compose.LottieConstants
import com.airbnb.lottie.compose.animateLottieCompositionAsState
import com.airbnb.lottie.compose.rememberLottieComposition
import com.nativephp.mobile.ui.nativerender.NativeUINode
import com.nativephp.mobile.ui.nativerender.NodeView
import java.io.File
import kotlin.math.PI
import kotlin.math.sin

internal data class PullToRefreshChildren(
        val indicator: NativeUINode?,
        val content: List<NativeUINode>,
)

/** Keep the declaration sentinels out of the scroll rows. */
internal fun splitPullToRefreshChildren(node: NativeUINode): PullToRefreshChildren {
    val indicator = node.children.firstOrNull { it.type == "pull_to_refresh_indicator" }
    return PullToRefreshChildren(
            indicator = indicator,
            content = node.children.filter { it.type != "pull_to_refresh_indicator" },
    )
}

/** Container silhouette with radii proportional to the box, matching the iOS shapes. */
internal fun pullIndicatorShape(shape: String): Shape =
        when (shape) {
            "capsule" -> RoundedCornerShape(percent = 50)
            "rounded" -> RoundedCornerShape(percent = 28)
            "blob" -> RoundedCornerShape(topStartPercent = 46, topEndPercent = 34, bottomEndPercent = 48, bottomStartPercent = 30)
            else -> CircleShape
        }

/**
 * Material's indicator box is a fixed 40dp square, where a capsule is just a circle.
 * Widening it first makes the capsule visible; Compose keeps the outer width constraint.
 */
internal fun Modifier.indicatorContainerWidth(shape: String): Modifier =
        if (shape == "capsule") width(64.dp) else this

/**
 * Degrees of one continuous turn that restarts every [durationMillis] while [active].
 * Returns 0 when inactive so idle indicators never schedule frames.
 */
@Composable
internal fun rememberLoopRotation(active: Boolean, durationMillis: Int, label: String): Float {
    val looping = active && durationMillis > 0
    val spec: AnimationSpec<Float> =
            if (looping) {
                infiniteRepeatable(tween(durationMillis = durationMillis, easing = LinearEasing), RepeatMode.Restart)
            } else {
                snap()
            }
    val rotation by animateFloatAsState(targetValue = if (looping) 360f else 0f, animationSpec = spec, label = label)
    return rotation
}

/** Refreshing always loops; `indeterminate` also loops while the finger is pulling. Results never loop. */
internal fun isIndicatorLooping(result: String, isRefreshing: Boolean, progressMode: String, progress: Float): Boolean =
        result == "idle" && (isRefreshing || (progressMode == "indeterminate" && progress > 0f))

/** Breathing scale derived from a loop rotation, used by the `pulse` motion. */
internal fun pulseScale(rotation: Float, amplitude: Float = 0.08f): Float =
        1f + amplitude * sin(rotation * PI.toFloat() / 180f)

@Composable
internal fun PullToRefreshSurface(
        shape: String,
        color: Color,
        modifier: Modifier = Modifier,
        content: @Composable () -> Unit,
) {
    Surface(
            modifier = modifier.clip(pullIndicatorShape(shape)),
            shape = pullIndicatorShape(shape),
            color = color,
            shadowElevation = 0.dp,
    ) {
        content()
    }
}

/** Render one phase's arbitrary EDGE tree as a native pull-to-refresh overlay. */
@Composable
internal fun PullToRefreshCustomIndicator(
        indicator: NativeUINode,
        progress: Float,
        isRefreshing: Boolean,
        result: String,
        size: Dp,
        reduceMotion: Boolean,
        motion: String,
        progressMode: String,
        animationDurationMillis: Int,
        modifier: Modifier = Modifier,
) {
    val phaseName =
            when {
                result == "success" || result == "error" -> result
                isRefreshing -> "refreshing"
                progress >= 1f -> "release"
                else -> "pulling"
            }
    // Keep the composable call ahead of the early return so its slot stays stable.
    val gestureProgress = progress.coerceIn(0f, 1f)
    val normalizedProgress = if (progressMode == "none") 1f else gestureProgress
    val looping = isIndicatorLooping(result, isRefreshing, progressMode, gestureProgress)
    val loopRotation =
            rememberLoopRotation(
                    active = looping && !reduceMotion && motion != "none",
                    durationMillis = animationDurationMillis,
                    label = "pull-to-refresh-layer-loop",
            )
    val phase = indicator.children.firstOrNull {
        it.type == "pull_to_refresh_phase" && it.props.getString("name", "pulling") == phaseName
    } ?: indicator.children.firstOrNull {
        it.type == "pull_to_refresh_phase" && it.props.getString("name", "pulling") == "pulling"
    } ?: return

    Box(modifier = modifier.size(size), contentAlignment = Alignment.Center) {
        phase.children.filter { it.type == "pull_to_refresh_layer" }.forEach { layer ->
            PullToRefreshLayerView(
                    layer = layer,
                    progress = normalizedProgress,
                    isRefreshing = isRefreshing,
                    looping = looping,
                    loopRotation = loopRotation,
                    indeterminate = progressMode == "indeterminate",
                    motion = motion,
                    reduceMotion = reduceMotion,
            )
        }
    }
}

@Composable
private fun PullToRefreshLayerView(
        layer: NativeUINode,
        progress: Float,
        isRefreshing: Boolean,
        looping: Boolean,
        loopRotation: Float,
        indeterminate: Boolean,
        motion: String,
        reduceMotion: Boolean,
) {
    val role = layer.props.getString("role", "artwork")
    val motionEnabled = !reduceMotion && motion != "none"
    val rotation =
            when {
                !motionEnabled -> 0f
                role == "progress" && (looping || indeterminate) -> loopRotation
                role == "progress" -> progress * 180f
                role == "track" && looping -> loopRotation
                role == "artwork" && looping && motion == "spin" -> loopRotation
                else -> 0f
            }
    val scale =
            when {
                !motionEnabled -> 1f
                role == "background" -> 0.82f + (progress * 0.18f)
                role == "artwork" && motion == "pulse" && looping -> pulseScale(loopRotation)
                role == "artwork" && (motion == "spring" || motion == "liquid") -> 0.86f + (progress * 0.14f)
                else -> 1f
            }
    val alpha = if (role == "label" && progress == 0f && !isRefreshing) 0f else 1f

    Box(
            modifier =
                    Modifier.fillMaxSize().graphicsLayer {
                        this.alpha = alpha
                        scaleX = scale
                        scaleY = scale
                        rotationZ = rotation
                    },
            contentAlignment = Alignment.Center,
    ) {
        val context = LocalContext.current
        val asset = layer.props.getString("asset", "")
        val lottie = layer.props.getString("lottie", "")
        val file = remember(context, asset, lottie) {
            resolveLocalIndicatorFile(context, if (lottie.isNotBlank()) lottie else asset)
        }

        when {
            lottie.isNotBlank() && file != null ->
                    IndicatorLottie(file, progress, isRefreshing, reduceMotion, Modifier.fillMaxSize())
            asset.isNotBlank() && file != null -> PullToRefreshLayerAsset(file)
        }

        layer.children.forEach { child -> NodeView(node = child) }
    }
}

/** Coil loader that decodes the PNG, SVG, and GIF formats the indicator accepts. */
@Composable
internal fun rememberIndicatorImageLoader(): ImageLoader {
    val context = LocalContext.current
    return remember(context) {
        ImageLoader.Builder(context)
                .components {
                    add(SvgDecoder.Factory())
                    if (Build.VERSION.SDK_INT >= 28) add(AnimatedImageDecoder.Factory()) else add(GifDecoder.Factory())
                }
                .build()
    }
}

@Composable
private fun PullToRefreshLayerAsset(file: File) {
    AsyncImage(
            model = file,
            imageLoader = rememberIndicatorImageLoader(),
            contentDescription = null,
            contentScale = ContentScale.Fit,
            modifier = Modifier.fillMaxSize(),
    )
}

/**
 * Scrub a local Lottie file with pull progress, then loop it while refreshing
 * unless reduced motion is on. [placeholder] draws until the file has loaded.
 */
@Composable
internal fun IndicatorLottie(
        file: File,
        progress: Float,
        isRefreshing: Boolean,
        reduceMotion: Boolean,
        modifier: Modifier = Modifier,
        placeholder: @Composable () -> Unit = {},
) {
    val composition by rememberLottieComposition(LottieCompositionSpec.File(file.absolutePath))
    val animatedProgress by
            animateLottieCompositionAsState(
                    composition = composition,
                    isPlaying = isRefreshing && !reduceMotion,
                    iterations = LottieConstants.IterateForever,
            )

    Box(modifier) {
        if (composition == null) {
            placeholder()
        } else {
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
        }
    }
}
