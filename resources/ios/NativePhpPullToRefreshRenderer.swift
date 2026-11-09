import SwiftUI
import UIKit
import Lottie
import SDWebImageSwiftUI
import SwiftDraw

/// Native pull-to-refresh container for NativePHP EDGE trees.
///
/// UIKit owns vertical scrolling and refresh activation. SwiftUI owns the EDGE
/// content and custom indicator, while UIKit reports overscroll for progress.
struct NativePhpPullToRefreshRenderer: View {
    let node: NativeUINode

    @Environment(\.nativeUITheme) private var theme
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    @State private var pullDistance: CGFloat = 0
    @State private var lifecycle = PullToRefreshLifecycleState()
    @State private var resolvedIndicatorAssetPath: String?
    @State private var resolvedIndicatorLottiePath: String?

    private var refreshPending: Bool { lifecycle.refreshPending }
    private var expiredResult: String { lifecycle.expiredResult }
    private var resultCycle: Int { lifecycle.resultCycle }

    /// PHP validates every enum value, so unknown strings only fall through to defaults.
    private var preset: String {
        node.props.getString("preset", default: "spring")
    }

    private var customIndicator: NativeUINode? {
        node.children.first { $0.type == PullToRefreshNodeType.indicator }
    }

    /// A composed indicator may override container visuals; otherwise the container's value applies.
    private func indicatorProp(_ key: String, default fallback: String) -> String {
        let inherited = node.props.getString(key, default: fallback)
        return customIndicator?.props.getString(key, default: inherited) ?? inherited
    }

    private var indicatorShape: String {
        indicatorProp("shape", default: "circle")
    }

    private var progressStyle: String {
        node.props.getString("progress_style", default: "ring")
    }

    private var indicatorMotion: String {
        indicatorProp("motion", default: "spring")
    }

    private var indicatorProgressMode: String {
        indicatorProp("progress_mode", default: "scrub")
    }

    private var threshold: CGFloat {
        max(1, CGFloat(node.props.getFloat("threshold", default: 88)))
    }

    private var indicatorSize: CGFloat {
        max(1, CGFloat(node.props.getFloat("indicator_size", default: 32)))
    }

    private var indicatorStrokeWidth: CGFloat {
        max(0.5, CGFloat(node.props.getFloat("indicator_stroke_width", default: 2.5)))
    }

    private var animationDurationMilliseconds: Int {
        max(0, node.props.getInt("animation_duration", default: 240))
    }

    private var animationDuration: Double {
        Double(animationDurationMilliseconds) / 1_000
    }

    private var refreshActionLabel: String {
        nonEmptyLabel("refresh_action_label", default: "Refresh")
    }

    private var pullLabel: String {
        nonEmptyLabel("pull_label", default: "Pull to refresh")
    }

    private var releaseLabel: String {
        nonEmptyLabel("release_label", default: "Release to refresh")
    }

    private var refreshingLabel: String {
        nonEmptyLabel("refreshing_label", default: "Refreshing")
    }

    private var successLabel: String {
        nonEmptyLabel("success_label", default: "Refresh complete")
    }

    private var errorLabel: String {
        nonEmptyLabel("error_label", default: "Refresh failed")
    }

    private var indicatorIconIos: String {
        node.props.getString("indicator_icon_ios", default: "").trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private var rawIndicatorAssetPath: String {
        node.props.getString("indicator_asset", default: "")
    }

    private var rawIndicatorLottiePath: String {
        node.props.getString("indicator_lottie", default: "")
    }

    private var isRefreshing: Bool {
        node.props.getBool("refreshing", default: false)
    }

    private var result: String {
        switch node.props.getString("result", default: "idle") {
        case "success", "error": node.props.getString("result", default: "idle")
        default: "idle"
        }
    }

    private var resultDurationMilliseconds: Int {
        max(0, node.props.getInt("result_duration", default: 500))
    }

    private var visibleResult: String {
        result == "idle" || result == expiredResult ? "idle" : result
    }

    /// Result the indicator draws. When a result expires the indicator retracts; it keeps
    /// the expired badge while it leaves instead of reverting to the empty pull glyph.
    private var indicatorResult: String {
        if visibleResult != "idle" { return visibleResult }
        guard !isInteractionLocked, pullDistance == 0, result == "success" || result == "error" else { return "idle" }
        return result
    }

    private var isInteractionLocked: Bool {
        isRefreshing || refreshPending
    }

    private var isIndicatorPinned: Bool {
        isInteractionLocked || visibleResult != "idle"
    }

    private var accentColor: Color {
        let props = node.props
        return props.has("accent_color")
            ? Color(argb: props.getColor("accent_color", default: 0))
            : theme.accent
    }

    private var containerColor: Color {
        let props = node.props
        return props.has("container_color")
            ? Color(argb: props.getColor("container_color", default: 0))
            : theme.surfaceVariant
    }

    private var trackColor: Color {
        let props = node.props
        guard props.has("track_color") else { return theme.outlineVariant }
        let rawString = props.getString("track_color", default: "")
        let parsed = props.getColor("track_color", default: 0)
        return rawString.isEmpty && parsed == 0 ? theme.outlineVariant : Color(argb: parsed)
    }

    private var indicatorProgress: CGFloat {
        min(1, max(0, pullDistance / threshold))
    }

    private var indicatorIsVisible: Bool {
        pullDistance > 0 || isIndicatorPinned
    }

    private var indicatorExposure: CGFloat {
        if isIndicatorPinned { return threshold }
        return min(threshold, max(0, pullDistance * 0.68))
    }

    var body: some View {
        PullToRefreshScrollHost(
            node: node,
            isRefreshing: isRefreshing || refreshPending,
            theme: theme,
            refreshActionLabel: refreshActionLabel,
            onRefresh: requestRefresh,
            onPullDistanceChange: { pullDistance = $0 }
        )
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .overlay(alignment: .top) {
            ZStack {
                pullToRefreshShape(indicatorShape, side: indicatorSize + 20).fill(containerColor)

                if let customIndicator {
                    PullToRefreshComposedIndicator(
                        indicator: customIndicator,
                        progress: indicatorProgress,
                        isRefreshing: isRefreshing || refreshPending,
                        result: indicatorResult,
                        motion: indicatorMotion,
                        progressMode: indicatorProgressMode,
                        animationDuration: animationDuration,
                        reduceMotion: reduceMotion
                    )
                    .frame(width: indicatorSize, height: indicatorSize)
                    // Layers are inserted when the phase changes, often while the container's
                    // offset is still animating. Grouping makes them ride that animation instead
                    // of appearing at the container's final position.
                    .geometryGroup()
                } else {
                    PullToRefreshIndicator(
                        preset: preset,
                        progressStyle: progressStyle,
                        motion: indicatorMotion,
                        progressMode: indicatorProgressMode,
                        progress: indicatorProgress,
                        isRefreshing: isRefreshing || refreshPending,
                        result: indicatorResult,
                        size: indicatorSize,
                        strokeWidth: indicatorStrokeWidth,
                        animationDuration: animationDuration,
                        tint: accentColor,
                        trackColor: trackColor,
                        successColor: theme.success,
                        errorColor: theme.destructive,
                        iconName: indicatorIconIos,
                        assetPath: resolvedIndicatorAssetPath,
                        lottiePath: resolvedIndicatorLottiePath
                    )
                    .frame(width: indicatorSize, height: indicatorSize)
                }
            }
            .frame(width: (indicatorSize + 20) * pullToRefreshContainerAspect(indicatorShape), height: indicatorSize + 20)
            .shadow(color: .black.opacity(0.08), radius: 4, x: 0, y: 2)
            .opacity(indicatorIsVisible ? indicatorOpacity : 0)
            .offset(y: indicatorExposure - indicatorSize - 20)
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(indicatorAccessibilityLabel)
            .accessibilityValue(indicatorAccessibilityValue)
            .accessibilityHidden(!indicatorIsVisible)
            .animation(indicatorAnimation, value: indicatorExposure)
            .animation(reduceMotion || animationDuration == 0 ? nil : .easeOut(duration: animationDuration), value: visibleResult)
        }
        .onChange(of: node) { updatedNode in
            guard refreshPending,
                  lifecycle.hostUpdated(pullToRefreshHostUpdate(from: node, to: updatedNode))
            else { return }
        }
        .onChange(of: isRefreshing) { newValue in
            lifecycle.observeRefreshing(newValue)
            if !newValue && !refreshPending {
                pullDistance = 0
            }
        }
        .onChange(of: result) { newValue in
            lifecycle.observeResult(newValue)
            if newValue != "idle" {
                pullDistance = 0
            }
        }
        .task(id: [rawIndicatorAssetPath, rawIndicatorLottiePath]) {
            resolvedIndicatorAssetPath = resolvePullToRefreshAssetPath(rawIndicatorAssetPath)
            resolvedIndicatorLottiePath = resolvePullToRefreshAssetPath(rawIndicatorLottiePath)
        }
        .task(id: "\(result)-\(resultDurationMilliseconds)-\(resultCycle)-\(isRefreshing)-\(refreshPending)-\(lifecycle.resultTimeoutGeneration)") {
            let expectedCycle = lifecycle.resultCycle
            let expectedGeneration = lifecycle.resultTimeoutGeneration
            guard lifecycle.prepareResultTimeout(result: result, isRefreshing: isRefreshing) else { return }
            guard resultDurationMilliseconds > 0 else {
                lifecycle.resultTimedOut(
                    result,
                    expectedCycle: expectedCycle,
                    expectedGeneration: expectedGeneration
                )
                return
            }

            try? await Task.sleep(for: .milliseconds(Int64(resultDurationMilliseconds)))
            guard !Task.isCancelled else { return }
            lifecycle.resultTimedOut(
                result,
                expectedCycle: expectedCycle,
                expectedGeneration: expectedGeneration
            )
        }
    }

    private var indicatorOpacity: Double {
        if isIndicatorPinned { return 1 }
        return min(1, Double(pullDistance / max(1, indicatorSize * 0.65)))
    }

    private var indicatorAccessibilityLabel: String {
        switch visibleResult {
        case "success": successLabel
        case "error": errorLabel
        case _ where isRefreshing || refreshPending: refreshingLabel
        default: indicatorProgress >= 1 ? releaseLabel : pullLabel
        }
    }

    private var indicatorAccessibilityValue: String {
        guard visibleResult == "idle", !isRefreshing, !refreshPending else { return "" }
        return indicatorProgress >= 1 ? releaseLabel : ""
    }

    /// Matches Android's timing per motion: bouncy springs, eased `liquid`, linear `spin`.
    private var indicatorAnimation: Animation? {
        guard !reduceMotion, animationDuration > 0 else { return nil }
        switch indicatorMotion {
        case "spring", "pulse": return .spring(response: animationDuration, dampingFraction: 0.72)
        case "liquid": return .easeInOut(duration: animationDuration)
        case "spin": return .linear(duration: animationDuration)
        default: return nil
        }
    }

    /// Accept a UIKit refresh request once and send its registered PHP callback.
    private func requestRefresh() -> Bool {
        let callbackId = node.props.getCallbackId("on_refresh")
        guard lifecycle.requestRefresh(
            callbackAvailable: callbackId != 0,
            isRefreshing: isRefreshing,
            visibleResult: visibleResult,
            result: result
        ) else { return false }

        pullDistance = 0
        NativeElementBridge.sendPressEvent(callbackId, nodeId: node.id)
        return true
    }

    /// Read optional localized accessibility text and guard against malformed serialized props.
    private func nonEmptyLabel(_ key: String, default fallback: String) -> String {
        let value = node.props.getString(key, default: fallback).trimmingCharacters(in: .whitespacesAndNewlines)
        return value.isEmpty ? fallback : value
    }
}

/// Hosts EDGE SwiftUI content in a UIKit scroll view so refresh activation remains system-native.
private struct PullToRefreshScrollHost: UIViewControllerRepresentable {
    let node: NativeUINode
    let isRefreshing: Bool
    let theme: NativeUITokens
    let refreshActionLabel: String
    let onRefresh: () -> Bool
    let onPullDistanceChange: (CGFloat) -> Void

    /// Create the UIKit scroll owner and install the EDGE rows, theme, and callbacks.
    func makeUIViewController(context: Context) -> PullToRefreshScrollController {
        let controller = PullToRefreshScrollController()
        controller.update(
            node: node,
            isRefreshing: isRefreshing,
            theme: theme,
            refreshActionLabel: refreshActionLabel,
            onRefresh: onRefresh,
            onPullDistanceChange: onPullDistanceChange
        )
        return controller
    }

    /// Forward state changes while avoiding table reloads for progress-only scroll updates.
    func updateUIViewController(_ controller: PullToRefreshScrollController, context: Context) {
        controller.update(
            node: node,
            isRefreshing: isRefreshing,
            theme: theme,
            refreshActionLabel: refreshActionLabel,
            onRefresh: onRefresh,
            onPullDistanceChange: onPullDistanceChange
        )
    }

    /// Remove delegates, accessibility actions, and refresh targets when the renderer leaves the tree.
    static func dismantleUIViewController(_ controller: PullToRefreshScrollController, coordinator: ()) {
        controller.teardown()
    }
}

/// Detect callback output without treating unrelated indicator-prop changes as completion.
private func pullToRefreshHostUpdate(from previous: NativeUINode, to updated: NativeUINode) -> PullToRefreshHostUpdate {
    PullToRefreshHostUpdate(
        refreshingChanged: previous.props.getBool("refreshing", default: false) != updated.props.getBool("refreshing", default: false),
        resultChanged: previous.props.getString("result", default: "idle") != updated.props.getString("result", default: "idle"),
        childrenChanged: pullToRefreshChildrenChanged(from: previous, to: updated),
        renderSequenceChanged: previous.props.getInt("render_sequence", default: 0) != updated.props.getInt("render_sequence", default: 0)
    )
}

/// The custom indicator is an overlay declaration, never a UIKit table row.
private func pullToRefreshContentChildren(_ node: NativeUINode) -> [NativeUINode] {
    node.children.filter { $0.type != PullToRefreshNodeType.indicator }
}

/// Detect refreshed EDGE children by identity-aware structural comparison.
private func pullToRefreshChildrenChanged(from previous: NativeUINode, to updated: NativeUINode) -> Bool {
    let previousChildren = pullToRefreshContentChildren(previous)
    let updatedChildren = pullToRefreshContentChildren(updated)
    guard previousChildren.count == updatedChildren.count else { return true }
    return zip(previousChildren, updatedChildren).contains { pair in
        !pair.0.deepEquals(pair.1)
    }
}

/// Owns native virtualized rows, scrolling, refresh control, and EDGE child rendering.
private final class PullToRefreshScrollController: UIViewController, UITableViewDataSource, UITableViewDelegate, UIScrollViewDelegate {
    private let tableView = UITableView(frame: .zero, style: .plain)
    private let refreshControl = UIRefreshControl()
    private var renderedNode: NativeUINode?
    private var renderedTheme: NativeUITokens?
    private var edgeChildren: [NativeUINode] = []
    private var theme = NativeUITokens.fallback
    private var refreshActionLabel = "Refresh"
    private var appIsRefreshing = false
    private var onRefresh: () -> Bool = { false }
    private var onPullDistanceChange: (CGFloat) -> Void = { _ in }

    override func loadView() {
        view = UIView()
        view.backgroundColor = .clear
        tableView.translatesAutoresizingMaskIntoConstraints = false
        tableView.backgroundColor = .clear
        tableView.alwaysBounceVertical = true
        tableView.bounces = true
        tableView.showsVerticalScrollIndicator = true
        tableView.contentInsetAdjustmentBehavior = .automatic
        tableView.separatorStyle = .none
        tableView.rowHeight = UITableView.automaticDimension
        tableView.estimatedRowHeight = 72
        tableView.register(UITableViewCell.self, forCellReuseIdentifier: "NativePhpPullToRefreshEDGECell")
        tableView.dataSource = self
        tableView.delegate = self
        refreshControl.tintColor = .clear
        refreshControl.accessibilityElementsHidden = true
        refreshControl.addTarget(self, action: #selector(didPullToRefresh), for: .valueChanged)
        tableView.refreshControl = refreshControl
        view.addSubview(tableView)
        NSLayoutConstraint.activate([
            tableView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            tableView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            tableView.topAnchor.constraint(equalTo: view.topAnchor),
            tableView.bottomAnchor.constraint(equalTo: view.bottomAnchor)
        ])
    }

    /// Update native state and reload virtualized rows only when content or its environment changes.
    func update(
        node: NativeUINode,
        isRefreshing: Bool,
        theme: NativeUITokens,
        refreshActionLabel: String,
        onRefresh: @escaping () -> Bool,
        onPullDistanceChange: @escaping (CGFloat) -> Void
    ) {
        loadViewIfNeeded()
        self.onRefresh = onRefresh
        self.onPullDistanceChange = onPullDistanceChange
        self.refreshActionLabel = refreshActionLabel

        let contentChanged: Bool
        if let renderedNode {
            contentChanged = pullToRefreshChildrenChanged(from: renderedNode, to: node)
        } else {
            contentChanged = true
        }

        if contentChanged || renderedTheme != theme {
            edgeChildren = pullToRefreshContentChildren(node)
            self.theme = theme
            tableView.reloadData()
        }

        renderedNode = node
        renderedTheme = theme

        tableView.accessibilityCustomActions = node.props.getCallbackId("on_refresh") == 0 ? nil : [
            UIAccessibilityCustomAction(name: refreshActionLabel) { [weak self] _ in
                guard let self else { return false }
                return self.onRefresh()
            }
        ]

        appIsRefreshing = isRefreshing
        if isRefreshing {
            if !refreshControl.isRefreshing {
                refreshControl.beginRefreshing()
            }
        } else if refreshControl.isRefreshing {
            refreshControl.endRefreshing()
            onPullDistanceChange(0)
        }
    }

    /// Detach UIKit callbacks and the refresh target when SwiftUI removes this renderer.
    func teardown() {
        tableView.delegate = nil
        tableView.dataSource = nil
        tableView.accessibilityCustomActions = nil
        refreshControl.removeTarget(self, action: #selector(didPullToRefresh), for: .valueChanged)
        renderedNode = nil
        renderedTheme = nil
        edgeChildren = []
    }

    /// Report top-edge overscroll to SwiftUI so custom indicators can track native pull progress.
    func scrollViewDidScroll(_ scrollView: UIScrollView) {
        let distance = max(0, -scrollView.contentOffset.y - scrollView.adjustedContentInset.top)
        onPullDistanceChange(distance)
    }

    /// Return one EDGE child per native table row so UIKit can reuse off-screen content.
    func tableView(_ tableView: UITableView, numberOfRowsInSection section: Int) -> Int {
        edgeChildren.count
    }

    /// Host the selected EDGE child in a self-sizing native cell with NativePHP environment values.
    func tableView(_ tableView: UITableView, cellForRowAt indexPath: IndexPath) -> UITableViewCell {
        let cell = tableView.dequeueReusableCell(withIdentifier: "NativePhpPullToRefreshEDGECell", for: indexPath)
        cell.backgroundColor = .clear
        cell.contentView.backgroundColor = .clear
        cell.selectionStyle = .none
        let child = edgeChildren[indexPath.row]
        cell.contentConfiguration = UIHostingConfiguration {
            NodeView(node: child)
                .equatable()
                .frame(maxWidth: .infinity, alignment: .leading)
                .environment(\.nativeUITheme, theme)
        }
        .margins(.all, 0)
        return cell
    }

    /// Forward UIKit's native release-to-refresh event through the PHP callback registry once.
    @objc private func didPullToRefresh() {
        guard !appIsRefreshing else {
            refreshControl.endRefreshing()
            return
        }

        guard onRefresh() else {
            refreshControl.endRefreshing()
            return
        }
    }

}

/// Render the selected preset or custom source and expose a single semantic status element.
private struct PullToRefreshIndicator: View {
    let preset: String
    let progressStyle: String
    let motion: String
    let progressMode: String
    let progress: CGFloat
    let isRefreshing: Bool
    let result: String
    let size: CGFloat
    let strokeWidth: CGFloat
    let animationDuration: Double
    let tint: Color
    let trackColor: Color
    let successColor: Color
    let errorColor: Color
    let iconName: String
    let assetPath: String?
    let lottiePath: String?

    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    private var looping: Bool {
        pullToRefreshIsLooping(result: result, isRefreshing: isRefreshing, progressMode: progressMode, progress: progress)
    }

    private var motionEnabled: Bool {
        !reduceMotion && motion != "none" && animationDuration > 0
    }

    /// What the glyph draws. Semantics stay on the gesture progress owned by the renderer.
    private var visualProgress: CGFloat {
        progressMode == "none" ? 1 : min(max(progress, 0), 1)
    }

    private var bouncyMotion: Bool {
        !reduceMotion && (motion == "spring" || motion == "pulse")
    }

    /// Lottie and GIF playback: off for reduced motion and `motion="none"`.
    private var stillMedia: Bool {
        reduceMotion || motion == "none"
    }

    var body: some View {
        // Resolved once per update, not on every animation tick below.
        let showsSystemIcon = !iconName.isEmpty && UIImage(systemName: iconName) != nil

        TimelineView(.animation(minimumInterval: 1.0 / 30.0, paused: !(looping && motionEnabled))) { context in
            let rotation = looping && motionEnabled
                ? pullToRefreshLoopRotation(at: context.date, cycle: animationDuration)
                : 0
            indicator(rotation: rotation, showsSystemIcon: showsSystemIcon)
                .scaleEffect(motion == "pulse" && looping && motionEnabled ? pullToRefreshPulseScale(rotation, amplitude: 0.06) : 1)
        }
        .frame(width: size, height: size)
    }

    /// Glyph with the result badge stacked above it. The swap is instant, as on Android:
    /// a cross-fade blends the glyph (e.g. the liquid fill) into the badge, and short
    /// result durations end before the badge is fully opaque.
    private func indicator(rotation: Double, showsSystemIcon: Bool) -> some View {
        let badge = resultBadge

        return ZStack {
            glyph(rotation: rotation, showsSystemIcon: showsSystemIcon)
                .opacity(badge == nil ? 1 : 0)
                .animation(nil, value: badge == nil)
            if let badge {
                // Palette rendering fills the symbol's cut-out mark, so nothing shows through it.
                Image(systemName: badge.symbol)
                    .symbolRenderingMode(.palette)
                    .foregroundStyle(.white, badge.color)
                    .font(.system(size: size * 0.76, weight: .semibold))
                    .zIndex(1)
                    .transition(.identity)
                    .accessibilityHidden(true)
            }
        }
    }

    private var resultBadge: (symbol: String, color: Color)? {
        switch result {
        case "success": return ("checkmark.circle.fill", successColor)
        case "error": return ("exclamationmark.circle.fill", errorColor)
        default: return nil
        }
    }

    /// Select Lottie, platform icon, asset, or built-in preset in a stable priority order.
    @ViewBuilder
    private func glyph(rotation: Double, showsSystemIcon: Bool) -> some View {
        if let lottiePath {
            PullToRefreshLottie(path: lottiePath, progress: visualProgress, isRefreshing: isRefreshing, reduceMotion: stillMedia) {
                presetGlyph(rotation: rotation)
            }
            .frame(width: size, height: size)
        } else if showsSystemIcon {
            ZStack {
                progressRing(rotation: rotation)
                Image(systemName: iconName)
                    .font(.system(size: size * 0.38, weight: .semibold))
                    .foregroundStyle(tint)
                    .rotationEffect(.degrees(isRefreshing && motionEnabled ? rotation : 0))
                    .scaleEffect(bouncyMotion ? 0.88 + (visualProgress * 0.16) : 1)
                    .accessibilityHidden(true)
            }
        } else if let assetPath {
            ZStack {
                progressRing(rotation: rotation)
                PullToRefreshLocalAsset(
                    path: assetPath,
                    isRefreshing: isRefreshing,
                    reduceMotion: stillMedia
                )
                .frame(width: size * 0.58, height: size * 0.58)
                .accessibilityHidden(true)
            }
        } else {
            presetGlyph(rotation: rotation)
        }
    }

    /// Draw an accessible-hidden progress track around the selected icon or image.
    @ViewBuilder
    private func progressRing(rotation: Double) -> some View {
        switch progressStyle {
        case "none":
            EmptyView()
        case "dots":
            dotsGlyph(rotation: rotation, diameter: size * 0.9)
                .accessibilityHidden(true)
        case "arc":
            orbitalGlyph(rotation: rotation, diameter: size * 0.9, showsCore: false)
                .accessibilityHidden(true)
        default:
            ZStack {
                Circle()
                    .stroke(trackColor, lineWidth: ringWidth)
                Circle()
                    .trim(from: 0, to: looping ? 0.72 : max(0.04, visualProgress))
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(.degrees(-90 + rotation))
            }
            .frame(width: size * 0.9, height: size * 0.9)
            .accessibilityHidden(true)
        }
    }

    /// Liquid filling the ring up to `fill`, with a wave that travels with `phase` (degrees).
    private func liquidGlyph(fill: CGFloat, phase: Double) -> some View {
        ZStack {
            PullToRefreshLiquidShape(fill: fill, phase: phase)
                .fill(tint)
                .clipShape(Circle())
            Circle().stroke(trackColor, lineWidth: ringWidth)
        }
        .frame(width: size * 0.84, height: size * 0.84)
    }

    /// Thin track, progress arc, and a satellite riding the arc's leading edge.
    private func orbitalGlyph(rotation: Double, diameter: CGFloat, showsCore: Bool = true) -> some View {
        let sweep: CGFloat = looping ? 110.0 / 360.0 : (300.0 / 360.0) * visualProgress
        let satellite = max(ringWidth * 2.2, diameter * 0.14)

        return ZStack {
            Circle()
                .stroke(trackColor, lineWidth: max(0.5, ringWidth * 0.5))
            Circle()
                .trim(from: 0, to: max(0.01, sweep))
                .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
            Circle()
                .fill(tint)
                .frame(width: satellite, height: satellite)
                .offset(x: diameter / 2)
                .rotationEffect(.degrees(Double(sweep) * 360))
            if showsCore {
                Circle()
                    .fill(tint.opacity(0.85))
                    .frame(width: diameter * 0.18, height: diameter * 0.18)
            }
        }
        .frame(width: diameter, height: diameter)
        .rotationEffect(.degrees(-90 + rotation))
    }

    /// Eight dots that fill with pull progress and chase around the ring while looping.
    private func dotsGlyph(rotation: Double, diameter: CGFloat) -> some View {
        let count = 8
        let dot = max(ringWidth * 1.8, diameter * 0.13)
        let lit = Int((visualProgress * CGFloat(count)).rounded(.up))
        let headIndex = ((Int(rotation / 360 * Double(count)) % count) + count) % count

        return ZStack {
            ForEach(0..<count, id: \.self) { index in
                let distance = ((headIndex - index) % count + count) % count
                Circle()
                    .fill(looping ? tint.opacity(max(0.2, 1 - Double(distance) * 0.16)) : (index < lit ? tint : trackColor))
                    .frame(width: dot, height: dot)
                    .offset(y: -(diameter - dot) / 2)
                    .rotationEffect(.degrees(Double(index) * 360 / Double(count)))
            }
        }
        .frame(width: diameter, height: diameter)
    }

    /// Clamp the configured stroke so it remains inside small indicators.
    private var ringWidth: CGFloat {
        min(max(0.5, strokeWidth), size * 0.25)
    }

    /// The built-in drawing for the current style, also shown while a Lottie file is loading.
    /// `rotation` is 0 unless the indicator is looping with motion enabled.
    @ViewBuilder
    private func presetGlyph(rotation: Double) -> some View {
        switch progressStyle {
        case "wave":
            liquidGlyph(fill: looping ? 0.72 : visualProgress, phase: rotation)
        case "arc":
            orbitalGlyph(rotation: rotation, diameter: size * 0.82)
        case "dots":
            dotsGlyph(rotation: rotation, diameter: size * 0.82)
        case "none":
            if looping {
                Circle()
                    .fill(tint)
                    .frame(width: size * 0.2, height: size * 0.2)
            } else {
                Image(systemName: "arrow.down")
                    .font(.system(size: size * 0.42, weight: .semibold))
                    .foregroundStyle(tint)
                    .rotationEffect(reduceMotion ? .zero : .degrees(progress >= 1 ? 180 : 0))
            }
        default:
            if preset == "minimal" {
                minimalGlyph(rotation: rotation)
            } else {
                springGlyph(rotation: rotation)
            }
        }
    }

    /// Thin arc with a center dot; a spinning half arc while looping.
    @ViewBuilder
    private func minimalGlyph(rotation: Double) -> some View {
        if looping {
            Circle()
                .trim(from: 0.08, to: 0.58)
                .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                .rotationEffect(motionEnabled ? .degrees(rotation) : .degrees(-45))
                .frame(width: size * 0.82, height: size * 0.82)
        } else {
            ZStack {
                Circle()
                    .trim(from: 0, to: max(0.05, visualProgress))
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                    .frame(width: size * 0.82, height: size * 0.82)
                Circle()
                    .fill(tint)
                    .frame(width: size * 0.1, height: size * 0.1)
            }
        }
    }

    /// Track, progress arc, and arrow; a spinning arc around a dot while looping.
    @ViewBuilder
    private func springGlyph(rotation: Double) -> some View {
        if looping {
            ZStack {
                Circle().stroke(trackColor, lineWidth: ringWidth)
                Circle()
                    .trim(from: 0.04, to: 0.72)
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(motionEnabled ? .degrees(rotation) : .degrees(-45))
                Circle().fill(tint).frame(width: size * 0.2, height: size * 0.2)
            }
            .frame(width: size * 0.78, height: size * 0.78)
            .scaleEffect(motion == "spring" && motionEnabled ? CGFloat(1 + sin(rotation * .pi / 180) * 0.055) : 1)
        } else {
            ZStack {
                Circle().stroke(trackColor, lineWidth: ringWidth)
                Circle()
                    .trim(from: 0, to: max(0.1, visualProgress))
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                Image(systemName: "arrow.down")
                    .font(.system(size: size * 0.34, weight: .semibold))
                    .foregroundStyle(tint)
                    .rotationEffect(reduceMotion ? .zero : .degrees(progress >= 1 ? 180 : 0))
            }
            .frame(width: size * 0.78, height: size * 0.78)
            .scaleEffect(bouncyMotion ? CGFloat(0.88 + min(0.16, Double(visualProgress) * 0.16)) : 1)
        }
    }
}

/// Renders the Blade-declared indicator tree selected by the native gesture lifecycle.
private struct PullToRefreshComposedIndicator: View {
    let indicator: NativeUINode
    let progress: CGFloat
    let isRefreshing: Bool
    let result: String
    let motion: String
    let progressMode: String
    let animationDuration: Double
    let reduceMotion: Bool

    private var activePhaseName: String {
        switch result {
        case "success", "error": return result
        default:
            if isRefreshing { return "refreshing" }
            return progress >= 1 ? "release" : "pulling"
        }
    }

    private var activePhase: NativeUINode? {
        indicator.children.first {
            $0.type == PullToRefreshNodeType.phase && $0.props.getString("name", default: "pulling") == activePhaseName
        } ?? indicator.children.first {
            $0.type == PullToRefreshNodeType.phase && $0.props.getString("name", default: "pulling") == "pulling"
        }
    }

    private var looping: Bool {
        pullToRefreshIsLooping(result: result, isRefreshing: isRefreshing, progressMode: progressMode, progress: progress)
    }

    private var animates: Bool {
        looping && !reduceMotion && motion != "none" && animationDuration > 0
    }

    var body: some View {
        let layers = activePhase?.children.filter { $0.type == PullToRefreshNodeType.layer } ?? []
        let normalizedProgress = progressMode == "none" ? 1 : min(1, max(0, progress))

        TimelineView(.animation(minimumInterval: 1.0 / 30.0, paused: !animates)) { context in
            let loopRotation = animates ? pullToRefreshLoopRotation(at: context.date, cycle: animationDuration) : 0

            ZStack {
                ForEach(layers, id: \.id) { layer in
                    PullToRefreshComposedLayer(
                        layer: layer,
                        progress: normalizedProgress,
                        isRefreshing: isRefreshing,
                        looping: looping,
                        loopRotation: loopRotation,
                        indeterminate: progressMode == "indeterminate",
                        motion: motion,
                        reduceMotion: reduceMotion
                    )
                    // Phases swap instantly, as on Android; a cross-fade would stack
                    // layers from several phases while the gesture moves quickly.
                    .transition(.identity)
                }
            }
        }
    }
}

/// Whether an EDGE node stretches along an axis (`w-full`, `h-1/2`, …) rather than wrapping its content.
private func pullToRefreshFillsAxis(_ mode: Int?) -> Bool {
    mode == SizeMode.fill || mode == SizeMode.percent
}

private struct PullToRefreshComposedLayer: View {
    let layer: NativeUINode
    let progress: CGFloat
    let isRefreshing: Bool
    let looping: Bool
    let loopRotation: Double
    let indeterminate: Bool
    let motion: String
    let reduceMotion: Bool

    /// Resolved once per path: the layer re-renders on every loop frame, and resolution touches the filesystem.
    @State private var assetPath: String?
    @State private var lottiePath: String?

    private var role: String { layer.props.getString("role", default: "artwork") }
    private var rawAssetPath: String { layer.props.getString("asset", default: "") }
    private var rawLottiePath: String { layer.props.getString("lottie", default: "") }
    private var motionEnabled: Bool { !reduceMotion && motion != "none" }

    private var rotation: Double {
        guard motionEnabled else { return 0 }
        switch role {
        case "progress" where looping || indeterminate: return loopRotation
        case "progress": return Double(progress * 180)
        case "track" where looping: return loopRotation
        case "artwork" where looping && motion == "spin": return loopRotation
        default: return 0
        }
    }

    private var scale: CGFloat {
        guard motionEnabled else { return 1 }
        switch role {
        case "background": return 0.82 + (progress * 0.18)
        case "artwork" where motion == "pulse" && looping: return pullToRefreshPulseScale(loopRotation)
        case "artwork" where motion == "spring" || motion == "liquid": return 0.86 + (progress * 0.14)
        default: return 1
        }
    }

    var body: some View {
        ZStack {
            if let lottiePath {
                PullToRefreshLottie(path: lottiePath, progress: progress, isRefreshing: isRefreshing, reduceMotion: !motionEnabled)
            } else if let assetPath {
                PullToRefreshLocalAsset(path: assetPath, isRefreshing: isRefreshing, reduceMotion: !motionEnabled)
                    .accessibilityHidden(true)
            }

            // Equatable lets SwiftUI skip unchanged EDGE subtrees on every animation tick.
            ForEach(layer.children, id: \.id) { child in
                NodeView(node: child).equatable()
                    // EDGE frames are unbounded and top-leading by default, which would pin
                    // an icon to the corner and make rotation orbit it. Wrap-sized children
                    // take their ideal size so the layer centers them; `w-full`/`h-full` still fill.
                    .fixedSize(
                        horizontal: !pullToRefreshFillsAxis(child.layout?.widthMode),
                        vertical: !pullToRefreshFillsAxis(child.layout?.heightMode)
                    )
            }
        }
        // Fill the indicator so rotation and scale pivot on its center, matching Android's centered Box.
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .opacity(role == "label" && progress == 0 && !isRefreshing ? 0 : 1)
        .scaleEffect(scale)
        .rotationEffect(.degrees(rotation))
        .task(id: [rawAssetPath, rawLottiePath]) {
            assetPath = resolvePullToRefreshAssetPath(rawAssetPath)
            lottiePath = resolvePullToRefreshAssetPath(rawLottiePath)
        }
    }
}

/// Scrubs a local Lottie file with pull progress, then loops it while refreshing
/// unless reduced motion is on. `placeholder` draws until the file has loaded.
private struct PullToRefreshLottie<Placeholder: View>: View {
    let path: String
    let progress: CGFloat
    let isRefreshing: Bool
    let reduceMotion: Bool
    @ViewBuilder let placeholder: () -> Placeholder

    @State private var animation: LottieAnimation?

    var body: some View {
        Group {
            if let animation {
                LottieView(animation: animation)
                    .playbackMode(playbackMode)
            } else {
                placeholder()
            }
        }
        .accessibilityHidden(true)
        .task(id: path) {
            animation = LottieAnimation.filepath(path)
        }
    }

    private var playbackMode: LottiePlaybackMode {
        if isRefreshing && !reduceMotion {
            return .playing(.fromProgress(0, toProgress: 1, loopMode: .loop))
        }
        return .paused(at: .progress(Double(min(max(progress, 0), 1))))
    }
}

extension PullToRefreshLottie where Placeholder == EmptyView {
    init(path: String, progress: CGFloat, isRefreshing: Bool, reduceMotion: Bool) {
        self.init(path: path, progress: progress, isRefreshing: isRefreshing, reduceMotion: reduceMotion) { EmptyView() }
    }
}

/// Refreshing always loops; `indeterminate` also loops while the finger is pulling. Results never loop.
private func pullToRefreshIsLooping(result: String, isRefreshing: Bool, progressMode: String, progress: CGFloat) -> Bool {
    result == "idle" && (isRefreshing || (progressMode == "indeterminate" && progress > 0))
}

/// EDGE node types this plugin declares in nativephp.json.
private enum PullToRefreshNodeType {
    static let indicator = "pull_to_refresh_indicator"
    static let phase = "pull_to_refresh_phase"
    static let layer = "pull_to_refresh_layer"
}

/// Degrees of a continuous turn that restarts every `cycle` seconds, kept small for precision.
private func pullToRefreshLoopRotation(at date: Date, cycle: Double) -> Double {
    let seconds = max(cycle, 0.001)
    return (date.timeIntervalSinceReferenceDate.truncatingRemainder(dividingBy: seconds) / seconds) * 360
}

/// Breathing scale derived from a loop rotation, used by the `pulse` motion.
private func pullToRefreshPulseScale(_ rotation: Double, amplitude: Double = 0.08) -> CGFloat {
    CGFloat(1 + amplitude * sin(rotation * .pi / 180))
}

/// Container silhouette with radii proportional to `side`, matching the Android shapes.
private func pullToRefreshShape(_ shape: String, side: CGFloat) -> AnyShape {
    switch shape {
    case "capsule": return AnyShape(Capsule())
    case "rounded": return AnyShape(RoundedRectangle(cornerRadius: side * 0.28, style: .continuous))
    case "blob": return AnyShape(UnevenRoundedRectangle(
        topLeadingRadius: side * 0.46,
        bottomLeadingRadius: side * 0.30,
        bottomTrailingRadius: side * 0.48,
        topTrailingRadius: side * 0.34,
        style: .continuous
    ))
    default: return AnyShape(Circle())
    }
}

/// A capsule only reads as a capsule when it is wider than tall.
private func pullToRefreshContainerAspect(_ shape: String) -> CGFloat {
    shape == "capsule" ? 1.6 : 1
}

/// Liquid body whose surface sits at `fill` (0...1 of the height) with a sine wave offset by `phase` degrees.
/// Mirrors Android's `drawLiquidProgress`.
private struct PullToRefreshLiquidShape: Shape {
    var fill: CGFloat
    var phase: Double

    var animatableData: AnimatablePair<CGFloat, Double> {
        get { AnimatablePair(fill, phase) }
        set { (fill, phase) = (newValue.first, newValue.second) }
    }

    func path(in rect: CGRect) -> Path {
        let radius = min(rect.width, rect.height) / 2
        let fillHeight = radius * 2 * min(max(fill, 0), 1)
        let waterline = rect.midY + radius - fillHeight
        let amplitude = min(radius * 0.15, fillHeight * 0.12)
        let segments = 24

        var path = Path()
        path.move(to: CGPoint(x: rect.midX - radius, y: rect.midY + radius))
        for step in 0...segments {
            let x = rect.midX - radius + radius * 2 * CGFloat(step) / CGFloat(segments)
            let angle = Double((x - rect.midX) / radius) * .pi * 1.5 + Double(fill) * .pi + phase * .pi / 180
            path.addLine(to: CGPoint(x: x, y: waterline + CGFloat(sin(angle)) * amplitude))
        }
        path.addLine(to: CGPoint(x: rect.midX + radius, y: rect.midY + radius))
        path.closeSubpath()
        return path
    }
}

/// Load only local consumer assets and honor the platform's reduced-motion preference.
private struct PullToRefreshLocalAsset: View {
    let path: String
    let isRefreshing: Bool
    let reduceMotion: Bool

    @State private var svg: SVG?

    private var isSVG: Bool {
        path.lowercased().hasSuffix(".svg")
    }

    private var isGIF: Bool {
        path.lowercased().hasSuffix(".gif")
    }

    var body: some View {
        Group {
            if isSVG {
                if let svg {
                    SVGView(svg: svg)
                        .resizable()
                        .scaledToFit()
                } else {
                    Color.clear
                }
            } else if isGIF {
                AnimatedImage(
                    url: URL(fileURLWithPath: path),
                    isAnimating: .constant(isRefreshing && !reduceMotion)
                )
                .resizable()
                .scaledToFit()
            } else if let image = NativeUIImageCache.image(atPath: path) {
                Image(uiImage: image)
                    .resizable()
                    .scaledToFit()
            } else {
                Image(systemName: "photo")
                    .resizable()
                    .scaledToFit()
                    .foregroundStyle(.secondary)
            }
        }
        .task(id: path) {
            guard isSVG else {
                svg = nil
                return
            }

            svg = SVG(fileURL: URL(fileURLWithPath: path))
        }
    }
}

/// Resolve an app-public relative path and reject symlinks or normalized paths escaping that root.
private func resolvePullToRefreshAssetPath(_ rawPath: String) -> String? {
    var path = rawPath.trimmingCharacters(in: .whitespacesAndNewlines).replacingOccurrences(of: "\\", with: "/")
    if path.hasPrefix("./") {
        path.removeFirst(2)
    }

    let segments = path.split(separator: "/", omittingEmptySubsequences: false)
    guard !path.isEmpty,
          !path.hasPrefix("/"),
          URL(string: path)?.scheme == nil,
          !segments.contains(where: { $0 == ".." || $0.isEmpty }),
          !path.unicodeScalars.contains(where: { $0.value == 0 }) else {
        return nil
    }

    let publicRoot = URL(fileURLWithPath: AppUpdateManager.shared.getAppPath(), isDirectory: true)
        .appendingPathComponent("public", isDirectory: true)
        .resolvingSymlinksInPath()
    let candidate = publicRoot
        .appendingPathComponent(path)
        .standardizedFileURL
        .resolvingSymlinksInPath()
    let rootPrefix = publicRoot.path.hasSuffix("/") ? publicRoot.path : publicRoot.path + "/"

    guard candidate.path.hasPrefix(rootPrefix),
          FileManager.default.fileExists(atPath: candidate.path) else {
        return nil
    }

    return candidate.path
}
