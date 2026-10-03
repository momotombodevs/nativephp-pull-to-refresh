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

    private var preset: String {
        switch node.props.getString("preset", default: "spring") {
        case "liquid", "minimal": node.props.getString("preset", default: "spring")
        default: "spring"
        }
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
            PullToRefreshIndicator(
                preset: preset,
                progress: indicatorProgress,
                isRefreshing: isRefreshing || refreshPending,
                result: visibleResult,
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
            .padding(10)
            .background(containerColor, in: Circle())
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

    private var indicatorAnimation: Animation? {
        guard !reduceMotion, animationDuration > 0 else { return nil }
        return .spring(response: animationDuration, dampingFraction: preset == "spring" ? 0.72 : 0.9)
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
        childrenChanged: pullToRefreshChildrenChanged(from: previous, to: updated)
    )
}

/// Detect refreshed EDGE children by identity-aware structural comparison.
private func pullToRefreshChildrenChanged(from previous: NativeUINode, to updated: NativeUINode) -> Bool {
    guard previous.children.count == updated.children.count else { return true }
    return zip(previous.children, updated.children).contains { pair in
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
            edgeChildren = node.children
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
    @State private var lottieAnimation: LottieAnimation?

    var body: some View {
        TimelineView(.animation(minimumInterval: 1.0 / 30.0, paused: !isRefreshing || reduceMotion || animationDuration == 0)) { context in
            let cycleSeconds = max(animationDuration, 0.001)
            let rotation = isRefreshing && !reduceMotion && animationDuration > 0
                ? context.date.timeIntervalSinceReferenceDate * 360 / cycleSeconds
                : 0
            indicator(rotation: rotation)
        }
        .frame(width: size, height: size)
        .task(id: lottiePath ?? "") {
            lottieAnimation = lottiePath.flatMap { LottieAnimation.filepath($0) }
        }
    }

    /// Select result, Lottie, platform icon, asset, or built-in preset in a stable priority order.
    @ViewBuilder
    private func indicator(rotation: Double) -> some View {
        if result == "success" {
            Image(systemName: "checkmark.circle.fill")
                .font(.system(size: size * 0.76, weight: .semibold))
                .foregroundStyle(successColor)
                .accessibilityHidden(true)
        } else if result == "error" {
            Image(systemName: "exclamationmark.circle.fill")
                .font(.system(size: size * 0.76, weight: .semibold))
                .foregroundStyle(errorColor)
                .accessibilityHidden(true)
        } else if let animation = lottieAnimation {
            LottieView(animation: animation)
                .playbackMode(lottiePlaybackMode)
                .frame(width: size, height: size)
                .accessibilityHidden(true)
        } else if !iconName.isEmpty, UIImage(systemName: iconName) != nil {
            ZStack {
                progressRing(rotation: rotation)
                Image(systemName: iconName)
                    .font(.system(size: size * 0.38, weight: .semibold))
                    .foregroundStyle(tint)
                    .rotationEffect(.degrees(isRefreshing && !reduceMotion ? rotation : 0))
                    .scaleEffect(preset == "spring" && !reduceMotion ? 0.88 + (min(progress, 1) * 0.16) : 1)
                    .accessibilityHidden(true)
            }
        } else if let assetPath {
            ZStack {
                progressRing(rotation: rotation)
                PullToRefreshLocalAsset(
                    path: assetPath,
                    isRefreshing: isRefreshing,
                    reduceMotion: reduceMotion
                )
                .frame(width: size * 0.58, height: size * 0.58)
                .accessibilityHidden(true)
            }
        } else if isRefreshing {
            activeGlyph(rotation: rotation)
        } else {
            pullingGlyph
        }
    }

    /// Scrub the custom animation with pull progress and loop it only while refreshing.
    private var lottiePlaybackMode: LottiePlaybackMode {
        if isRefreshing && !reduceMotion {
            return .playing(.fromProgress(0, toProgress: 1, loopMode: .loop))
        }
        return .paused(at: .progress(Double(min(max(progress, 0), 1))))
    }

    /// Draw an accessible-hidden progress track around the selected icon or image.
    private func progressRing(rotation: Double) -> some View {
        ZStack {
            Circle()
                .stroke(trackColor, lineWidth: ringWidth)
            Circle()
                .trim(from: 0, to: isRefreshing ? 0.72 : max(0.04, min(max(progress, 0), 1)))
                .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                .rotationEffect(.degrees(-90 + (isRefreshing && !reduceMotion ? rotation : 0)))
        }
        .frame(width: size * 0.9, height: size * 0.9)
        .accessibilityHidden(true)
    }

    /// Clamp the configured stroke so it remains inside small indicators.
    private var ringWidth: CGFloat {
        min(max(0.5, strokeWidth), size * 0.25)
    }

    /// Draw the selected preset's progress state before the native refresh control activates.
    @ViewBuilder
    private var pullingGlyph: some View {
        switch preset {
        case "liquid":
            ZStack {
                Circle().stroke(trackColor, lineWidth: ringWidth)
                Circle()
                    .trim(from: 0, to: max(0.08, progress))
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                Circle()
                    .fill(tint.opacity(0.9))
                    .frame(width: size * 0.2, height: size * 0.2)
                    .offset(y: size * 0.22)
                Circle()
                    .fill(.white.opacity(0.28))
                    .frame(width: size * 0.08, height: size * 0.08)
                    .offset(x: -size * 0.16, y: size * 0.1)
            }
            .frame(width: size * 0.72, height: size * 0.72)
            .rotationEffect(reduceMotion ? .zero : .degrees(Double(progress) * 12))
        case "minimal":
            ZStack {
                Circle()
                    .trim(from: 0, to: max(0.05, progress))
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                    .frame(width: size * 0.82, height: size * 0.82)
                Circle()
                    .fill(tint)
                    .frame(width: size * 0.1, height: size * 0.1)
            }
        default:
            ZStack {
                Circle().stroke(trackColor, lineWidth: ringWidth)
                Circle()
                    .trim(from: 0, to: max(0.1, progress))
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                Image(systemName: "arrow.down")
                    .font(.system(size: size * 0.34, weight: .semibold))
                    .foregroundStyle(tint)
                    .rotationEffect(reduceMotion ? .zero : .degrees(progress >= 1 ? 180 : 0))
            }
            .frame(width: size * 0.78, height: size * 0.78)
            .scaleEffect(reduceMotion ? 1 : CGFloat(0.88 + min(0.16, Double(progress) * 0.16)))
        }
    }

    /// Animate the selected preset while UIKit reports an active refresh.
    @ViewBuilder
    private func activeGlyph(rotation: Double) -> some View {
        switch preset {
        case "liquid":
            ZStack {
                Circle().stroke(trackColor, lineWidth: ringWidth)
                Circle().fill(tint.opacity(0.9)).frame(width: size * 0.23, height: size * 0.23)
                    .offset(y: size * 0.22)
            }
            .frame(width: size * 0.72, height: size * 0.72)
            .rotationEffect(reduceMotion ? .zero : .degrees(rotation))
        case "minimal":
            Circle()
                .trim(from: 0.08, to: 0.58)
                .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                .rotationEffect(reduceMotion ? .degrees(-45) : .degrees(rotation))
                .frame(width: size * 0.82, height: size * 0.82)
        default:
            ZStack {
                Circle().stroke(trackColor, lineWidth: ringWidth)
                Circle()
                    .trim(from: 0.04, to: 0.72)
                    .stroke(tint, style: StrokeStyle(lineWidth: ringWidth, lineCap: .round))
                    .rotationEffect(reduceMotion ? .degrees(-45) : .degrees(rotation))
                Circle().fill(tint).frame(width: size * 0.2, height: size * 0.2)
            }
            .frame(width: size * 0.78, height: size * 0.78)
            .scaleEffect(reduceMotion ? 1 : CGFloat(1 + sin(rotation * .pi / 180) * 0.055))
        }
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
