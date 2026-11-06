package dev.momotombo.plugins.nativephp_pull_to_refresh.ui

import android.animation.ValueAnimator
import androidx.compose.animation.core.spring
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.pulltorefresh.PullToRefreshDefaults
import androidx.compose.material3.pulltorefresh.PullToRefreshState
import androidx.compose.material3.pulltorefresh.pullToRefresh
import androidx.compose.material3.pulltorefresh.rememberPullToRefreshState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.CustomAccessibilityAction
import androidx.compose.ui.semantics.customActions
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.nativephp.mobile.ui.nativerender.NativeElementBridge
import com.nativephp.mobile.ui.nativerender.NativeUINode
import com.nativephp.mobile.ui.nativerender.NodeView
import com.nativephp.plugins.native_ui.LocalNativeUITheme
import kotlinx.coroutines.delay

/** NativePHP EDGE pull-to-refresh container backed by Material 3 nested scroll. */
@OptIn(ExperimentalMaterial3Api::class)
object NativePhpPullToRefreshRenderer {
    private const val IDLE = PullToRefreshLifecycleState.IDLE
    private const val SUCCESS = "success"
    private const val ERROR = "error"

    /** Render one scroll-owning EDGE node and route accepted pulls to its registered PHP callback. */
    @Composable
    fun Render(node: NativeUINode, modifier: Modifier) {
        val props = node.props
        // PHP validates every enum value, so unknown strings only fall through to defaults.
        val preset = props.getString("preset", "spring")
        val declaredChildren = splitPullToRefreshChildren(node)
        val customIndicator = declaredChildren.indicator
        /** A composed indicator may override container visuals; otherwise the container's value applies. */
        fun indicatorProp(key: String, default: String): String =
                props.getString(key, default).let { inherited -> customIndicator?.props?.getString(key, inherited) ?: inherited }
        val shape = indicatorProp("shape", "circle")
        val progressStyle = props.getString("progress_style", "ring")
        val motion = indicatorProp("motion", "spring")
        val progressMode = indicatorProp("progress_mode", "scrub")
        val threshold = props.getFloat("threshold", 88f).coerceAtLeast(1f).dp
        val indicatorSize = props.getFloat("indicator_size", 32f).coerceAtLeast(1f).dp
        val indicatorStrokeWidth = props.getFloat("indicator_stroke_width", 2.5f).coerceAtLeast(0.5f).dp
        val animationDurationMillis = props.getInt("animation_duration", 240).coerceAtLeast(0)
        val refreshing = props.getBool("refreshing")
        val result =
                props.getString("result", IDLE).lowercase().let {
                    if (it == SUCCESS || it == ERROR) it else IDLE
                }
        val resultDuration = props.getInt("result_duration", 500).coerceAtLeast(0).toLong()
        val renderSequence = props.getInt("render_sequence", 0)
        val refreshActionLabel = props.getString("refresh_action_label", "Refresh").ifBlank { "Refresh" }
        val labels =
                IndicatorLabels(
                        pull = props.getString("pull_label", "Pull to refresh").ifBlank { "Pull to refresh" },
                        release = props.getString("release_label", "Release to refresh").ifBlank { "Release to refresh" },
                        refreshing = props.getString("refreshing_label", "Refreshing").ifBlank { "Refreshing" },
                        success = props.getString("success_label", "Refresh complete").ifBlank { "Refresh complete" },
                        error = props.getString("error_label", "Refresh failed").ifBlank { "Refresh failed" },
                )
        val refreshCallback = props.getCallbackId("on_refresh")
        val theme = LocalNativeUITheme.current
        val accentColor =
                if (props.has("accent_color")) {
                    Color(props.getColor("accent_color", 0))
                } else {
                    theme.accent
                }
        val containerColor =
                if (props.has("container_color")) {
                    Color(props.getColor("container_color", 0))
                } else {
                    theme.surfaceVariant
                }
        val trackColor =
                if (!props.has("track_color")) {
                    theme.outlineVariant
                } else {
                    val rawTrackColor = props.getString("track_color", "")
                    val parsedTrackColor = props.getColor("track_color", 0)
                    if (rawTrackColor.isBlank() && parsedTrackColor == 0) theme.outlineVariant
                    else Color(parsedTrackColor)
                }
        val context = LocalContext.current
        val androidIconName = props.getString("indicator_icon_android", "").trim().lowercase()
        val assetPath = props.getString("indicator_asset", "").trim()
        val lottiePath = props.getString("indicator_lottie", "").trim()
        val assetFile =
                remember(context, assetPath) { resolveLocalIndicatorFile(context, assetPath) }
        val lottieFile =
                remember(context, lottiePath) { resolveLocalIndicatorFile(context, lottiePath) }
        val reduceMotion = remember { !ValueAnimator.areAnimatorsEnabled() }

        val pullState = rememberPullToRefreshState()
        val resultState = rememberPullToRefreshState()
        val lifecycleState = remember(node.id) {
            mutableStateOf(PullToRefreshLifecycleState<NativeUINode>())
        }
        val lifecycle = lifecycleState.value

        // A host tree update also acknowledges a completed callback. Repeated
        // identical results (for example, success followed by success) may
        // leave both wire props unchanged even though the refreshed children
        // changed, so keying only on `refreshing` and `result` can lock forever.
        LaunchedEffect(node, refreshing, result) {
            lifecycleState.value = lifecycleState.value.hostUpdated(
                children = declaredChildren.content,
                isRefreshing = refreshing,
                result = result,
                renderSequence = renderSequence,
            )
        }

        // Keep a separate per-gesture latch until the pull animation has
        // returned to rest and the host has finished loading. A fast callback
        // can otherwise release localPending while the same drag is still
        // active, allowing Material's nested-scroll callback to fire again.
        LaunchedEffect(node.id, pullState.distanceFraction, refreshing, lifecycle.localPending) {
            lifecycleState.value = lifecycleState.value.gestureSettled(
                isRefreshing = refreshing,
                pullFraction = pullState.distanceFraction,
            )
        }

        LaunchedEffect(refreshing, result, resultDuration, lifecycle.localPending, lifecycle.resultCycle) {
            val displayedLifecycle = lifecycleState.value.showResult(result, isRefreshing = refreshing)
            lifecycleState.value = displayedLifecycle
            val timeoutGeneration = displayedLifecycle.resultTimeoutGeneration
            if (displayedLifecycle.visibleResult != IDLE) {
                delay(resultDuration)
                lifecycleState.value = lifecycleState.value.resultTimedOut(timeoutGeneration)
            }
        }

        val busy = refreshing || lifecycle.localPending
        val resultVisible = lifecycle.visibleResult != IDLE
        val showResultIndicator = resultVisible && !busy && pullState.distanceFraction <= 0f
        val interactionLocked = busy
        val indicatorState = PullIndicatorState(
            preset = preset,
            progressStyle = progressStyle,
            motion = motion,
            progressMode = progressMode,
            progress = pullState.distanceFraction,
            isRefreshing = busy,
            result = IDLE,
            indicatorSize = indicatorSize,
            indicatorStrokeWidth = indicatorStrokeWidth,
            animationDurationMillis = animationDurationMillis,
            labels = labels,
            accentColor = accentColor,
            trackColor = trackColor,
            successColor = theme.success,
            errorColor = theme.destructive,
            reduceMotion = reduceMotion,
            androidIconName = androidIconName,
            assetFile = assetFile,
            lottieFile = lottieFile,
        )
        /** The composed indicator when one is declared, otherwise the built-in preset. */
        val indicatorContent: @Composable (Float, Boolean, String) -> Unit = { progress, isRefreshing, shownResult ->
            if (customIndicator != null) {
                PullToRefreshCustomIndicator(
                        indicator = customIndicator,
                        progress = progress,
                        isRefreshing = isRefreshing,
                        result = shownResult,
                        size = indicatorSize,
                        reduceMotion = reduceMotion,
                        motion = motion,
                        progressMode = progressMode,
                        animationDurationMillis = animationDurationMillis,
                        labels = labels,
                )
            } else {
                PullIndicator(indicatorState.copy(progress = progress, isRefreshing = isRefreshing, result = shownResult))
            }
        }
        val requestRefresh: () -> Boolean = {
            val request = lifecycleState.value.requestRefresh(
                callbackAvailable = refreshCallback != 0,
                isRefreshing = interactionLocked,
                children = declaredChildren.content,
                renderSequence = renderSequence,
            )
            if (!request.accepted) {
                false
            } else {
                lifecycleState.value = request.state
                NativeElementBridge.sendPressEvent(refreshCallback, node.id)
                true
            }
        }

        LaunchedEffect(node.id, showResultIndicator) {
            if (showResultIndicator) resultState.animateToThreshold()
            else resultState.animateToHidden()
        }

        // Material keeps the pull fraction independent from the app loading
        // flag. Explicitly settle it after the callback completes so a
        // completed refresh cannot reappear as a stale loading indicator.
        LaunchedEffect(node.id, busy, result, lifecycle.resultCycle) {
            if (!busy) pullState.animateToHidden()
        }

        Box(
                modifier =
                        modifier.fillMaxSize()
                                .clipToBounds()
                                .pullToRefresh(
                                        isRefreshing = busy,
                                        state = pullState,
                                        enabled = refreshCallback != 0 && !interactionLocked,
                                        threshold = threshold,
                                        onRefresh = {
                                            if (!lifecycleState.value.refreshTriggeredForGesture && requestRefresh()) {
                                                lifecycleState.value = lifecycleState.value.markGestureTriggered()
                                            }
                                        },
                                )
                                .semantics {
                                    stateDescription =
                                            when {
                                                lifecycle.visibleResult == SUCCESS -> labels.success
                                                lifecycle.visibleResult == ERROR -> labels.error
                                                busy -> labels.refreshing
                                                pullState.distanceFraction >= 1f -> labels.release
                                                else -> labels.pull
                                            }
                                    customActions =
                                            if (refreshCallback == 0) {
                                                emptyList()
                                            } else {
                                                listOf(
                                                        CustomAccessibilityAction(refreshActionLabel) {
                                                            requestRefresh()
                                                        }
                                                )
                                            }
                                },
        ) {
            LazyColumn(modifier = Modifier.fillMaxSize()) {
                items(declaredChildren.content, key = { child -> child.id }) { child ->
                    NodeView(node = child)
                }
            }

            ShapedIndicatorBox(pullState, busy, threshold, shape, indicatorSize, containerColor) {
                indicatorContent(pullState.distanceFraction, busy, IDLE)
            }

            if (showResultIndicator) {
                ShapedIndicatorBox(resultState, isRefreshing = true, threshold, shape, indicatorSize, containerColor) {
                    indicatorContent(1f, false, lifecycle.visibleResult)
                }
            }
        }
    }

    /** Material indicator box clipped and sized to the selected shape, filled with the container color. */
    @Composable
    private fun BoxScope.ShapedIndicatorBox(
            state: PullToRefreshState,
            isRefreshing: Boolean,
            threshold: Dp,
            shape: String,
            indicatorSize: Dp,
            containerColor: Color,
            content: @Composable () -> Unit,
    ) {
        PullToRefreshDefaults.IndicatorBox(
                modifier = Modifier.align(Alignment.TopCenter).indicatorContainerSize(shape, indicatorSize),
                state = state,
                isRefreshing = isRefreshing,
                maxDistance = threshold,
                shape = pullIndicatorShape(shape),
                containerColor = Color.Transparent,
                elevation = PullToRefreshDefaults.Elevation,
        ) {
            PullToRefreshSurface(shape = shape, color = containerColor, modifier = Modifier.fillMaxSize(), content = content)
        }
    }
}
