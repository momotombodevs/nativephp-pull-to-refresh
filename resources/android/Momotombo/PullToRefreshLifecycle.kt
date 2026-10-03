package dev.momotombo.plugins.nativephp_pull_to_refresh.ui

/** Immutable refresh lifecycle state shared by the Compose gesture and result indicators. */
internal data class PullToRefreshLifecycleState<T>(
    val localPending: Boolean = false,
    val pendingChildren: List<T>? = null,
    val refreshTriggeredForGesture: Boolean = false,
    val visibleResult: String = IDLE,
    val resultCycle: Int = 0,
    val resultTimeoutGeneration: Int = 0,
) {
    /** Accept one request until the host starts or publishes its response. */
    fun requestRefresh(
        callbackAvailable: Boolean,
        isRefreshing: Boolean,
        children: List<T>,
    ): PullToRefreshRequest<T> {
        if (!callbackAvailable || isRefreshing || localPending) {
            return PullToRefreshRequest(this, accepted = false)
        }

        return PullToRefreshRequest(
            state = copy(
                localPending = true,
                pendingChildren = children.toList(),
                visibleResult = IDLE,
                resultTimeoutGeneration = resultTimeoutGeneration + 1,
            ),
            accepted = true,
        )
    }

    /** Release a request when refreshing, a result, or changed children acknowledge the callback. */
    fun hostUpdated(
        children: List<T>,
        isRefreshing: Boolean,
        result: String,
    ): PullToRefreshLifecycleState<T> {
        if (!localPending) return this

        val childrenChanged = pendingChildren?.let { it != children } == true
        if (!isRefreshing && result == IDLE && !childrenChanged) return this

        return copy(
            localPending = false,
            pendingChildren = null,
            resultCycle = if (childrenChanged) resultCycle + 1 else resultCycle,
            resultTimeoutGeneration = resultTimeoutGeneration + 1,
        )
    }

    /** Keep the accepted gesture locked until its pull animation settles back at rest. */
    fun gestureSettled(
        isRefreshing: Boolean,
        pullFraction: Float,
    ): PullToRefreshLifecycleState<T> {
        if (isRefreshing || localPending || !(pullFraction <= 0f) || !refreshTriggeredForGesture) return this
        return copy(refreshTriggeredForGesture = false)
    }

    fun markGestureTriggered(): PullToRefreshLifecycleState<T> =
        if (refreshTriggeredForGesture) this else copy(refreshTriggeredForGesture = true)

    /** Start a result display timer and invalidate any timer from an earlier lifecycle. */
    fun showResult(
        result: String,
        isRefreshing: Boolean,
    ): PullToRefreshLifecycleState<T> =
        copy(
            visibleResult = if (result == IDLE || isRefreshing || localPending) IDLE else result,
            resultTimeoutGeneration = resultTimeoutGeneration + 1,
        )

    /** Ignore a timeout if another result timer has since taken ownership. */
    fun resultTimedOut(generation: Int): PullToRefreshLifecycleState<T> {
        if (generation != resultTimeoutGeneration || visibleResult == IDLE) return this
        return copy(visibleResult = IDLE)
    }

    companion object {
        const val IDLE = "idle"
    }
}

internal data class PullToRefreshRequest<T>(
    val state: PullToRefreshLifecycleState<T>,
    val accepted: Boolean,
)
