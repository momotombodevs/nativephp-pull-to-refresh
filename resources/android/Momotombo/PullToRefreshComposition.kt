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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.runtime.State
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
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.ProgressBarRangeInfo
import androidx.compose.ui.semantics.clearAndSetSemantics
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.progressBarRangeInfo
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.max
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

/** EDGE node types this plugin declares in nativephp.json. */
internal object PullToRefreshNodeType {
    const val INDICATOR = "pull_to_refresh_indicator"
    const val PHASE = "pull_to_refresh_phase"
    const val LAYER = "pull_to_refresh_layer"
}

internal data class PullToRefreshChildren(
        val indicator: NativeUINode?,
        val content: List<NativeUINode>,
)

/** Keep the declaration sentinels out of the scroll rows. */
internal fun splitPullToRefreshChildren(node: NativeUINode): PullToRefreshChildren {
    val (indicators, content) = node.children.partition { it.type == PullToRefreshNodeType.INDICATOR }
    return PullToRefreshChildren(indicator = indicators.firstOrNull(), content = content)
}

/** Localized announcements shared by the built-in and composed indicators. */
internal data class IndicatorLabels(
        val pull: String,
        val release: String,
        val refreshing: String,
        val success: String,
        val error: String,
)

/**
 * One accessibility element for the whole indicator, whatever it draws.
 * Clearing child semantics keeps composed EDGE layers out of TalkBack's focus order.
 */
internal fun Modifier.refreshIndicatorSemantics(
        labels: IndicatorLabels,
        progress: Float,
        isRefreshing: Boolean,
        result: String,
): Modifier = clearAndSetSemantics {
    contentDescription =
            when {
                result == "success" -> labels.success
                result == "error" -> labels.error
                isRefreshing -> labels.refreshing
                progress >= 1f -> labels.release
                else -> labels.pull
            }
    stateDescription =
            when {
                result == "success" -> "Complete"
                result == "error" -> "Failed"
                isRefreshing -> "In progress"
                progress >= 1f -> "Ready"
                progress > 0f -> "Pulling"
                else -> "Idle"
            }
    if (isRefreshing || result != "idle") liveRegion = LiveRegionMode.Polite
    if (result == "idle") {
        progressBarRangeInfo =
                if (isRefreshing) ProgressBarRangeInfo.Indeterminate else ProgressBarRangeInfo(progress, 0f..1f)
    }
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
 * Size Material's indicator box from `indicator-size` instead of its fixed 40dp,
 * and widen it 1.6× for a capsule. Compose keeps this outer constraint over the
 * box's own default size.
 */
internal fun Modifier.indicatorContainerSize(shape: String, indicatorSize: Dp): Modifier {
    val height = max(40.dp, indicatorSize + 8.dp)
    return size(width = if (shape == "capsule") height * 1.6f else height, height = height)
}

/**
 * Degrees of one continuous turn that restarts every [durationMillis] while [active].
 * Stays at 0 when inactive so idle indicators never schedule frames. Read the
 * returned state inside draw or `graphicsLayer` blocks so each frame only redraws.
 */
@Composable
internal fun rememberLoopRotation(active: Boolean, durationMillis: Int, label: String): State<Float> {
    val looping = active && durationMillis > 0
    val spec: AnimationSpec<Float> =
            if (looping) {
                infiniteRepeatable(tween(durationMillis = durationMillis, easing = LinearEasing), RepeatMode.Restart)
            } else {
                snap()
            }
    return animateFloatAsState(targetValue = if (looping) 360f else 0f, animationSpec = spec, label = label)
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
        labels: IndicatorLabels,
        modifier: Modifier = Modifier,
) {
    val gestureProgress = progress.coerceIn(0f, 1f)
    val normalizedProgress = if (progressMode == "none") 1f else gestureProgress
    val looping = isIndicatorLooping(result, isRefreshing, progressMode, gestureProgress)
    val motionEnabled = !reduceMotion && motion != "none"
    // Keep the composable call ahead of the early return so its slot stays stable.
    val loopRotation =
            rememberLoopRotation(
                    active = looping && motionEnabled,
                    durationMillis = animationDurationMillis,
                    label = "pull-to-refresh-layer-loop",
            )
    val phase = activePhase(indicator, result, isRefreshing, gestureProgress) ?: return

    Box(
            modifier =
                    modifier.size(size)
                            .refreshIndicatorSemantics(labels, gestureProgress, isRefreshing, result),
            contentAlignment = Alignment.Center,
    ) {
        phase.children.filter { it.type == PullToRefreshNodeType.LAYER }.forEach { layer ->
            PullToRefreshLayerView(
                    layer = layer,
                    progress = normalizedProgress,
                    isRefreshing = isRefreshing,
                    looping = looping,
                    loopRotation = loopRotation,
                    indeterminate = progressMode == "indeterminate",
                    motion = motion,
                    motionEnabled = motionEnabled,
            )
        }
    }
}

/** The phase matching the lifecycle, falling back to `pulling` for phases the author left out. */
private fun activePhase(indicator: NativeUINode, result: String, isRefreshing: Boolean, progress: Float): NativeUINode? {
    val wanted =
            when {
                result == "success" || result == "error" -> result
                isRefreshing -> "refreshing"
                progress >= 1f -> "release"
                else -> "pulling"
            }
    val phases = indicator.children.filter { it.type == PullToRefreshNodeType.PHASE }
    return phases.firstOrNull { it.props.getString("name", "pulling") == wanted }
            ?: phases.firstOrNull { it.props.getString("name", "pulling") == "pulling" }
}

@Composable
private fun PullToRefreshLayerView(
        layer: NativeUINode,
        progress: Float,
        isRefreshing: Boolean,
        looping: Boolean,
        loopRotation: State<Float>,
        indeterminate: Boolean,
        motion: String,
        motionEnabled: Boolean,
) {
    val role = layer.props.getString("role", "artwork")
    val alpha = if (role == "label" && progress == 0f && !isRefreshing) 0f else 1f

    Box(
            modifier =
                    Modifier.fillMaxSize().graphicsLayer {
                        // Reading the loop here invalidates only this layer's drawing each frame.
                        val turn = loopRotation.value
                        this.alpha = alpha
                        val scale = layerScale(role, motion, motionEnabled, looping, progress, turn)
                        scaleX = scale
                        scaleY = scale
                        rotationZ = layerRotation(role, motion, motionEnabled, looping, indeterminate, progress, turn)
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
                    IndicatorLottie(file, progress, isRefreshing, animate = motionEnabled, modifier = Modifier.fillMaxSize())
            asset.isNotBlank() && file != null ->
                    AsyncImage(
                            model = file,
                            imageLoader = rememberIndicatorImageLoader(animated = motionEnabled),
                            contentDescription = null,
                            contentScale = ContentScale.Fit,
                            modifier = Modifier.fillMaxSize(),
                    )
        }

        layer.children.forEach { child -> NodeView(node = child) }
    }
}

/** How a composed layer turns: progress scrubs or loops, track and spinning artwork loop. */
private fun layerRotation(
        role: String,
        motion: String,
        motionEnabled: Boolean,
        looping: Boolean,
        indeterminate: Boolean,
        progress: Float,
        loopRotation: Float,
): Float =
        when {
            !motionEnabled -> 0f
            role == "progress" && (looping || indeterminate) -> loopRotation
            role == "progress" -> progress * 180f
            role == "track" && looping -> loopRotation
            role == "artwork" && looping && motion == "spin" -> loopRotation
            else -> 0f
        }

/** How a composed layer scales: the background grows with the pull, artwork follows the motion. */
private fun layerScale(
        role: String,
        motion: String,
        motionEnabled: Boolean,
        looping: Boolean,
        progress: Float,
        loopRotation: Float,
): Float =
        when {
            !motionEnabled -> 1f
            role == "background" -> 0.82f + (progress * 0.18f)
            role == "artwork" && motion == "pulse" && looping -> pulseScale(loopRotation)
            role == "artwork" && (motion == "spring" || motion == "liquid") -> 0.86f + (progress * 0.14f)
            else -> 1f
        }

/**
 * Coil loader for the PNG, SVG, and GIF formats the indicator accepts. With
 * [animated] false GIFs decode as their first frame, honoring `motion="none"`
 * and reduced motion.
 */
@Composable
internal fun rememberIndicatorImageLoader(animated: Boolean): ImageLoader {
    val context = LocalContext.current
    return remember(context, animated) {
        ImageLoader.Builder(context)
                .components {
                    add(SvgDecoder.Factory())
                    if (animated) {
                        if (Build.VERSION.SDK_INT >= 28) add(AnimatedImageDecoder.Factory()) else add(GifDecoder.Factory())
                    }
                }
                .build()
    }
}

/**
 * Scrub a local Lottie file with pull progress, then loop it while refreshing
 * when [animate] allows it (false for reduced motion and `motion="none"`).
 * [placeholder] draws until the file has loaded.
 */
@Composable
internal fun IndicatorLottie(
        file: File,
        progress: Float,
        isRefreshing: Boolean,
        animate: Boolean,
        modifier: Modifier = Modifier,
        placeholder: @Composable () -> Unit = {},
) {
    val composition by rememberLottieComposition(LottieCompositionSpec.File(file.absolutePath))
    val animatedProgress by
            animateLottieCompositionAsState(
                    composition = composition,
                    isPlaying = isRefreshing && animate,
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
                            isRefreshing && !animate -> 1f
                            isRefreshing -> animatedProgress
                            else -> progress.coerceIn(0f, 1f)
                        }
                    },
                    modifier = Modifier.fillMaxSize(),
            )
        }
    }
}
