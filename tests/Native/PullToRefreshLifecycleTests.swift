// Compiled together with resources/ios/PullToRefreshLifecycle.swift by tests/NativeLifecycleTest.php.
import Foundation

var failures = 0

func check(_ name: String, _ condition: Bool) {
    if !condition {
        failures += 1
        print("FAIL \(name)")
    }
}

func update(refreshing: Bool = false, result: Bool = false, children: Bool = false, render: Bool = false) -> PullToRefreshHostUpdate {
    PullToRefreshHostUpdate(refreshingChanged: refreshing, resultChanged: result, childrenChanged: children, renderSequenceChanged: render)
}

/// A lifecycle holding one accepted request issued while a success result was still visible.
func pending() -> PullToRefreshLifecycleState {
    var lifecycle = PullToRefreshLifecycleState()
    _ = lifecycle.requestRefresh(callbackAvailable: true, isRefreshing: false, visibleResult: "success", result: "success")
    return lifecycle
}

var idle = PullToRefreshLifecycleState()
check("rejects a request without a callback", !idle.requestRefresh(callbackAvailable: false, isRefreshing: false, visibleResult: "idle", result: "idle"))
check("rejects a request while the host is refreshing", !idle.requestRefresh(callbackAvailable: true, isRefreshing: true, visibleResult: "idle", result: "idle"))

var locked = pending()
check("accepts a request and locks the gesture", locked.refreshPending)
check("rejects a second request while one is pending", !locked.requestRefresh(callbackAvailable: true, isRefreshing: false, visibleResult: "idle", result: "idle"))
check("expires the visible result when a new request starts", locked.expiredResult == "success")

var unchanged = pending()
check("an update without any signal does not acknowledge", !unchanged.hostUpdated(update()))
check("stays pending when nothing acknowledges the callback", unchanged.refreshPending)

var rendered = pending()
check("a newer render acknowledges a no-change refresh", rendered.hostUpdated(update(render: true)))
check("releases the lock after the render", !rendered.refreshPending)
check("a newer render starts a new result cycle", rendered.resultCycle == 1)
check("re-shows a repeated success after the render", rendered.prepareResultTimeout(result: "success", isRefreshing: false) && rendered.expiredResult == "idle")

for (name, signal) in [("children", update(children: true)), ("result", update(result: true)), ("refreshing", update(refreshing: true))] {
    var lifecycle = pending()
    check("a \(name) change acknowledges the callback", lifecycle.hostUpdated(signal) && !lifecycle.refreshPending)
}

var loading = pending()
loading.observeRefreshing(true)
check("the host's loading flag releases the request", !loading.refreshPending)

var cancelled = pending()
cancelled.cancelRefreshRequest()
check("a declined callback releases the request", !cancelled.refreshPending)

var timed = rendered
let cycle = timed.resultCycle
let generation = timed.resultTimeoutGeneration
check("ignores a timeout from an older result timer", !timed.resultTimedOut("success", expectedCycle: cycle, expectedGeneration: generation - 1))
check("expires the result on its own timer", timed.resultTimedOut("success", expectedCycle: cycle, expectedGeneration: generation) && timed.expiredResult == "success")

if failures > 0 {
    print("\(failures) Swift lifecycle check(s) failed")
    exit(1)
}
print("OK Swift lifecycle")
