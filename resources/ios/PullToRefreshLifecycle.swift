/// Owns the refresh lock, host acknowledgement, and temporary result lifetime for one renderer.
struct PullToRefreshLifecycleState: Equatable {
    private(set) var refreshPending = false
    private(set) var expiredResult = "idle"
    private(set) var resultCycle = 0
    private(set) var resultTimeoutGeneration = 0

    /// Accept a request only when the host callback is available and no refresh is already active.
    mutating func requestRefresh(
        callbackAvailable: Bool,
        isRefreshing: Bool,
        visibleResult: String,
        result: String
    ) -> Bool {
        guard callbackAvailable, !isRefreshing, !refreshPending else { return false }

        if visibleResult != "idle" {
            expiredResult = result
        }
        refreshPending = true
        resultTimeoutGeneration += 1
        return true
    }

    /// Complete a pending callback when host props or its rendered children acknowledge the response.
    mutating func hostUpdated(_ update: PullToRefreshHostUpdate) -> Bool {
        guard refreshPending, update.acknowledgesCallback else { return false }
        refreshPending = false
        resultCycle += 1
        resultTimeoutGeneration += 1
        return true
    }

    /// Follow the host's loading prop and invalidate any timer started before that update.
    mutating func observeRefreshing(_ isRefreshing: Bool) {
        guard isRefreshing, refreshPending else { return }
        refreshPending = false
        resultTimeoutGeneration += 1
    }

    /// A published result acknowledges a request even when the result string repeats.
    mutating func observeResult(_ result: String) {
        guard result != "idle", refreshPending else { return }
        refreshPending = false
        resultTimeoutGeneration += 1
    }

    /// Cancel a native request that the callback adapter declined to send.
    mutating func cancelRefreshRequest() {
        guard refreshPending else { return }
        refreshPending = false
        resultTimeoutGeneration += 1
    }

    /// Prepare visibility before a timeout task starts; return whether a timer should run.
    mutating func prepareResultTimeout(result: String, isRefreshing: Bool) -> Bool {
        guard result != "idle", !isRefreshing, !refreshPending else {
            expiredResult = result
            return false
        }

        expiredResult = "idle"
        return true
    }

    /// Expire only the result timer that still owns this lifecycle cycle.
    @discardableResult
    mutating func resultTimedOut(
        _ result: String,
        expectedCycle: Int,
        expectedGeneration: Int
    ) -> Bool {
        guard resultCycle == expectedCycle, resultTimeoutGeneration == expectedGeneration else { return false }
        expiredResult = result
        return true
    }
}

/// Host changes that can acknowledge a callback without depending on NativeUINode internals.
struct PullToRefreshHostUpdate: Equatable {
    let refreshingChanged: Bool
    let resultChanged: Bool
    let childrenChanged: Bool

    var acknowledgesCallback: Bool {
        refreshingChanged || resultChanged || childrenChanged
    }
}
