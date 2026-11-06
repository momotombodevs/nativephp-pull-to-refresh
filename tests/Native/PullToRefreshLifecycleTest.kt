// Compiled together with resources/android/Momotombo/PullToRefreshLifecycle.kt by tests/NativeLifecycleTest.php.
package dev.momotombo.plugins.nativephp_pull_to_refresh.ui

private var failures = 0

private fun check(name: String, condition: Boolean) {
    if (!condition) {
        failures++
        println("FAIL $name")
    }
}

private val rows = listOf("row-1", "row-2")

/** A state holding one accepted request issued while render sequence 7 was on screen. */
private fun pending(): PullToRefreshLifecycleState<String> =
    PullToRefreshLifecycleState<String>()
        .requestRefresh(callbackAvailable = true, isRefreshing = false, children = rows, renderSequence = 7)
        .state

fun main() {
    val idle = PullToRefreshLifecycleState<String>()

    check("rejects a request without a callback", !idle.requestRefresh(false, false, rows, 1).accepted)
    check("rejects a request while the host is refreshing", !idle.requestRefresh(true, true, rows, 1).accepted)
    check("rejects a second request while one is pending", !pending().requestRefresh(true, false, rows, 7).accepted)
    check("accepts a request and locks the gesture", pending().localPending)

    val unchanged = pending().hostUpdated(rows, isRefreshing = false, result = "idle", renderSequence = 7)
    check("stays pending when nothing acknowledges the callback", unchanged.localPending)

    val rendered = pending().hostUpdated(rows, isRefreshing = false, result = "idle", renderSequence = 8)
    check("a newer render acknowledges a no-change refresh", !rendered.localPending)
    check("a newer render starts a new result cycle", rendered.resultCycle == 1)

    val reloaded = pending().hostUpdated(listOf("row-0") + rows, isRefreshing = false, result = "idle", renderSequence = 7)
    check("changed children acknowledge the callback", !reloaded.localPending && reloaded.resultCycle == 1)

    val loading = pending().hostUpdated(rows, isRefreshing = true, result = "idle", renderSequence = 7)
    check("the host's loading flag acknowledges without a new result cycle", !loading.localPending && loading.resultCycle == 0)

    check("hides the result while a request is pending", pending().showResult("success", isRefreshing = false).visibleResult == "idle")
    val shown = rendered.showResult("success", isRefreshing = false)
    check("shows a repeated success once the render acknowledges it", shown.visibleResult == "success")

    val stale = shown.resultTimedOut(shown.resultTimeoutGeneration - 1)
    check("ignores a timeout from an older result timer", stale.visibleResult == "success")
    check("expires the result on its own timer", shown.resultTimedOut(shown.resultTimeoutGeneration).visibleResult == "idle")

    val triggered = idle.markGestureTriggered()
    check("keeps the gesture latch while the pull is still visible", triggered.gestureSettled(false, 0.4f).refreshTriggeredForGesture)
    check("keeps the gesture latch while the request is pending", pending().markGestureTriggered().gestureSettled(false, 0f).refreshTriggeredForGesture)
    check("releases the gesture latch once the pull settles", !triggered.gestureSettled(false, 0f).refreshTriggeredForGesture)

    if (failures > 0) {
        println("$failures Kotlin lifecycle check(s) failed")
        kotlin.system.exitProcess(1)
    }
    println("OK Kotlin lifecycle")
}
