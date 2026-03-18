// Network Map - application bootstrap
(function (global) {
  "use strict";

  const NM = (global.NetworkMap = global.NetworkMap || {});
  const filters = NM.filters || {};
  const state =
    (global.KNMState =
      global.KNMState ||
      {
        root: null,
        rawData: null,
        rawNodeMap: null,
        currentGraph: { nodes: [], edges: [] },
        cy: null,
        resizeHandler: null,
        summaryData: { incoming: [], outgoing: [] },
        hasDrawnGraph: false,
        initialized: false,
      });

  function getEl(id) {
    return document.getElementById(id);
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");
  }

  function ensureStateDefaults() {
    if (!(state.rawNodeMap instanceof Map)) {
      state.rawNodeMap = new Map();
    }

    if (!state.currentGraph) {
      state.currentGraph = { nodes: [], edges: [] };
    }

    if (!state.summaryData) {
      state.summaryData = { incoming: [], outgoing: [] };
    }

    if (typeof state.hasDrawnGraph !== "boolean") {
      state.hasDrawnGraph = false;
    }
  }

  function buildLayout(root) {
    if (!root || root.dataset.knmLayoutBuilt === "1") {
      return;
    }

    root.classList.add("knm-root");
    root.innerHTML = `
      <section class="knm-panel">
        <div class="knm-panel-header">
          <h2>Network map</h2>
        </div>

        <div class="knm-form-grid">
          <label class="knm-form-field">
            <span>Host scope</span>
            <select id="knm-hostSelect">
              <option value="">All hosts</option>
            </select>
          </label>

          <label class="knm-form-field">
            <span>Source filter</span>
            <input id="knm-filterSrc" type="text" placeholder="hostname or IP">
          </label>

          <label class="knm-form-field">
            <span>Destination filter</span>
            <input id="knm-filterDst" type="text" placeholder="hostname or IP">
          </label>

          <label class="knm-form-field">
            <span>Port filter</span>
            <input id="knm-filterPort" type="text" placeholder="443 or 80-443">
          </label>

          <label class="knm-form-field">
            <span>Excluded IPs / CIDRs / ranges</span>
            <input id="knm-filterIp" type="text" placeholder="10.0.0.0/8,192.168.1.10-192.168.1.40">
          </label>

          <label class="knm-form-field">
            <span>Minimum separation</span>
            <input id="knm-minSep" type="number" value="50" min="10" max="500" step="5">
          </label>

          <label class="knm-form-field">
            <span>Horizontal scale</span>
            <input id="knm-scaleX" type="number" value="1.0" min="0.1" max="5" step="0.1">
          </label>

          <label class="knm-form-field">
            <span>Vertical scale</span>
            <input id="knm-scaleY" type="number" value="1.0" min="0.1" max="5" step="0.1">
          </label>
        </div>

        <div class="knm-checkbox-row">
          <label class="knm-checkbox">
            <input id="knm-excludeNoisePorts" type="checkbox" checked>
            <span>Hide RPC / high ports</span>
          </label>

          <label class="knm-checkbox">
            <input id="knm-excludePub" type="checkbox">
            <span>Exclude public IPs</span>
          </label>
        </div>

        <div class="knm-help">
          Leave <strong>Host scope</strong> empty for the full graph. When a host is selected, only
          traffic where that host is the source or destination is shown.
        </div>

        <div class="knm-actions">
          <button id="knm-btnApply" class="knm-btn" type="button">Draw graph</button>
          <button id="knm-btnRefreshData" class="knm-btn knm-btn-secondary" type="button">Refresh data</button>
        </div>

        <div id="knm-dataStatus" class="knm-status" aria-live="polite">Loading network map…</div>
      </section>

      <div class="knm-main">
        <section class="knm-panel knm-graph-panel">
          <div id="knm-loading" class="knm-loading" hidden>Loading…</div>
          <div id="knm-cy" aria-live="polite"></div>
        </section>

        <aside class="knm-sidebar">
          <section id="knm-summary" class="knm-panel" hidden>
            <div class="knm-panel-header">
              <h3 id="knm-summaryTitle">Traffic summary</h3>

              <div class="knm-panel-actions">
                <button id="knm-minimizeSummary" class="knm-panel-toggle" type="button">Collapse</button>
                <button id="knm-closeSummary" class="knm-panel-toggle" type="button">Close</button>
              </div>
            </div>

            <div id="knm-summaryFilters">
              <div class="knm-form-grid">
                <label class="knm-form-field">
                  <span>Summary source filter</span>
                  <input id="knm-sumFilterSrc" type="text" placeholder="token or !exclude">
                </label>

                <label class="knm-form-field">
                  <span>Summary destination filter</span>
                  <input id="knm-sumFilterDst" type="text" placeholder="token or !exclude">
                </label>

                <label class="knm-form-field">
                  <span>Summary port filter</span>
                  <input id="knm-sumFilterPort" type="text" placeholder="443,80-443,!135">
                </label>
              </div>

              <div class="knm-help">
                Summary filters support comma-separated tokens. Prefix a token with <code>!</code> to exclude it.
              </div>
            </div>

            <pre id="knm-summaryContent" class="knm-summary-content"></pre>
          </section>
        </aside>
      </div>
    `;

    root.dataset.knmLayoutBuilt = "1";
  }

  function showBusy(show) {
    const overlay = getEl("knm-loading");
    const btnApply = getEl("knm-btnApply");
    const btnRefresh = getEl("knm-btnRefreshData");

    if (overlay) {
      overlay.hidden = !show;
    }

    [btnApply, btnRefresh].forEach((button) => {
      if (button) {
        button.disabled = !!show;
      }
    });
  }

  function buildNodeMap(nodes) {
    const map = new Map();

    (nodes || []).forEach((node) => {
      const id = node && node.data && node.data.id;

      if (id) {
        map.set(id, node);
      }
    });

    return map;
  }

  function sanitizeNetworkMapData(data) {
    const normalized = data && typeof data === "object" ? data : {};

    if (!Array.isArray(normalized.nodes)) {
      normalized.nodes = [];
    }

    if (!Array.isArray(normalized.edges)) {
      normalized.edges = [];
    }

    if (!normalized.meta || typeof normalized.meta !== "object") {
      normalized.meta = {};
    }

    normalized.nodes = normalized.nodes
      .filter((node) => node && node.data)
      .map((node) => {
        if (!node.data.id && node.data.label) {
          node.data.id = node.data.label;
        }

        if (!node.data.label && node.data.id) {
          node.data.label = node.data.id;
        }

        return node;
      })
      .filter((node) => !!node.data.id);

    normalized.edges = normalized.edges
      .filter((edge) => edge && edge.data && edge.data.source && edge.data.target)
      .map((edge, index) => {
        if (!edge.data.id) {
          edge.data.id = `e${index + 1}`;
        }

        return edge;
      });

    return normalized;
  }

  function populateHostSelect(nodes) {
    const select = getEl("knm-hostSelect");

    if (!select) {
      return;
    }

    const previousValue = select.value;
    const validIds = new Set();

    select.innerHTML = "";

    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = "All hosts";
    select.appendChild(allOption);

    (nodes || [])
      .slice()
      .sort((a, b) => {
        const left = String(a?.data?.label || a?.data?.id || "").toLowerCase();
        const right = String(b?.data?.label || b?.data?.id || "").toLowerCase();

        return left.localeCompare(right);
      })
      .forEach((node) => {
        const id = node?.data?.id;

        if (!id) {
          return;
        }

        validIds.add(id);

        const option = document.createElement("option");
        option.value = id;
        option.textContent = node?.data?.label || id;
        select.appendChild(option);
      });

    if (previousValue && validIds.has(previousValue)) {
      select.value = previousValue;
    }
  }

  function formatLocalDate(value) {
    if (!value) {
      return "unknown";
    }

    if (typeof value === "number" && Number.isFinite(value)) {
      try {
        return new Date(value * 1000).toLocaleString();
      } catch (error) {
        return String(value);
      }
    }

        const root = state.root || getEl("network-map-root");

    if (!Number.isNaN(date.getTime())) {
      return date.toLocaleString();
    }

    return String(value);
  }

  function formatAge(seconds) {
    const value = Number(seconds);

    if (!Number.isFinite(value) || value < 0) {
      return "";
    }

    if (value < 60) {
      return `${Math.round(value)}s`;
    }

    if (value < 3600) {
      return `${Math.round(value / 60)}m`;
    }

    return `${Math.round(value / 3600)}h`;
  }

  function setStatus(message, isError = false) {
    const status = getEl("knm-dataStatus");

    if (!status) {
      return;
    }

    status.textContent = message;
    status.classList.toggle("knm-status-error", !!isError);
  }

  function buildStatusText(meta) {
    const parts = [];

    if (meta.generated_at) {
      parts.push(`Updated: ${formatLocalDate(meta.generated_at)}`);
    } else if (meta.generated_at_iso) {
      parts.push(`Updated: ${formatLocalDate(meta.generated_at_iso)}`);
    }

    if (meta.time_from && meta.time_till) {
      parts.push(`Window: ${formatLocalDate(meta.time_from)} → ${formatLocalDate(meta.time_till)}`);
    } else if (meta.history_window_hours) {
      parts.push(`Window: last ${meta.history_window_hours}h`);
    }

    if (meta.nodes_count !== undefined) {
      parts.push(`Nodes: ${meta.nodes_count}`);
    }

    if (meta.edges_count !== undefined) {
      parts.push(`Edges: ${meta.edges_count}`);
    }

    if (meta.cached) {
      const age = formatAge(meta.cache_age_seconds);
      parts.push(age ? `Cache: yes (${age})` : "Cache: yes");
    } else {
      parts.push("Cache: fresh");
    }

    if (meta.stale) {
      parts.push("Using stale cache");
    }

    if (meta.warning) {
      parts.push(`Warning: ${meta.warning}`);
    }

    return parts.join(" | ");
  }

  function clearFadedGraph() {
    if (state.cy) {
      state.cy.elements().removeClass("faded");
    }
  }

  function hidePanels() {
    const summary = getEl("knm-summary");

    clearFadedGraph();

    if (summary) {
      summary.hidden = true;
    }
  }

  function setGraphPlaceholder(message) {
    const cyContainer = getEl("knm-cy");

    if (!cyContainer) {
      return;
    }

    cyContainer.innerHTML = `<div class="knm-graph-empty">${escapeHtml(message)}</div>`;
  }

  function readFilterSettings() {
    return {
      host: (getEl("knm-hostSelect") || {}).value || "",
      srcTokens: filters.parseListFilter
        ? filters.parseListFilter((getEl("knm-filterSrc") || {}).value || "")
        : [],
      dstTokens: filters.parseListFilter
        ? filters.parseListFilter((getEl("knm-filterDst") || {}).value || "")
        : [],
      portMatcher: filters.parsePortFilter
        ? filters.parsePortFilter(((getEl("knm-filterPort") || {}).value || "").trim())
        : null,
      excludePublic: !!(getEl("knm-excludePub") || {}).checked,
      excludeNoisePorts: (getEl("knm-excludeNoisePorts") || {}).checked !== false,
      ipFilters: filters.parseIpFilters
        ? filters.parseIpFilters(((getEl("knm-filterIp") || {}).value || "").trim())
        : [],
      minSep: Math.max(10, Number.parseInt(((getEl("knm-minSep") || {}).value || "50").trim(), 10) || 50),
      sx: Math.max(0.1, Number.parseFloat(((getEl("knm-scaleX") || {}).value || "1.0").trim()) || 1.0),
      sy: Math.max(0.1, Number.parseFloat(((getEl("knm-scaleY") || {}).value || "1.0").trim()) || 1.0),
    };
  }

  function applyFiltersAndDraw(options = {}) {
    const { showNoEdgesAlert = true } = options;

    if (!state.rawData) {
      setGraphPlaceholder("No network map data is loaded yet.");
      return false;
    }

    hidePanels();

    const settings = readFilterSettings();
    let subgraph = null;

    if (settings.host && typeof NM.buildSubgraph === "function") {
      subgraph = NM.buildSubgraph(
        settings.host,
        settings.srcTokens,
        settings.dstTokens,
        settings.portMatcher,
        settings.excludePublic,
        settings.excludeNoisePorts,
        settings.ipFilters
      );
    } else if (typeof NM.buildGlobalSubgraph === "function") {
      subgraph = NM.buildGlobalSubgraph(
        settings.srcTokens,
        settings.dstTokens,
        settings.portMatcher,
        settings.excludePublic,
        settings.excludeNoisePorts,
        settings.ipFilters
      );
    }

    if (!subgraph || !Array.isArray(subgraph.nodes) || !Array.isArray(subgraph.edges)) {
      setGraphPlaceholder("Failed to build the graph from the current filters.");
      state.hasDrawnGraph = false;
      return false;
    }

    if (typeof global.cytoscape === "undefined") {
      setGraphPlaceholder("Cytoscape failed to load.");
      state.hasDrawnGraph = false;
      return false;
    }

    if (typeof NM.drawGraph !== "function") {
      setGraphPlaceholder("Graph rendering code failed to load.");
      state.hasDrawnGraph = false;
      return false;
    }

    const drawn = NM.drawGraph({
      nodes: subgraph.nodes,
      edges: subgraph.edges,
      minSep: settings.minSep,
      sx: settings.sx,
      sy: settings.sy,
      showNoEdgesAlert,
    });

    state.hasDrawnGraph = drawn === true;

    if (!state.hasDrawnGraph) {
      if (!subgraph.edges.length) {
        setGraphPlaceholder("No edges matched the current filters.");
      } else {
        setGraphPlaceholder("The graph could not be rendered.");
      }
    }

    return state.hasDrawnGraph;
  }

  function withQuery(baseUrl, params) {
    const url = new URL(baseUrl, global.location.href);

    Object.entries(params || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        url.searchParams.set(key, value);
      }
    });

    return url.toString();
  }

  async function fetchJson(url) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
      },
    });

    let payload = null;

    try {
      payload = await response.json();
    } catch (error) {
      if (!response.ok) {
        throw new Error(`Request failed with HTTP ${response.status}.`);
      }

      throw new Error("The server did not return valid JSON.");
    }

    if (payload && payload.error) {
      throw new Error(payload.error.message || "Unknown API error.");
    }

    if (!response.ok) {
      throw new Error(`Request failed with HTTP ${response.status}.`);
    }

    return payload;
  }

  async function fetchNetworkMap(options = {}) {
    const {
      force = false,
      redraw = true,
      showBusyOverlay = true,
      showNoEdgesAlert = false,
    } = options;
    const root = state.root || getEl("network-map-root");

    if (!root) {
      return null;
    }

    if (!root.dataset.dataUrl) {
      setStatus("The module view is missing the data endpoint URL.", true);
      return null;
    }

    const url = force ? withQuery(root.dataset.dataUrl, { force: "1" }) : root.dataset.dataUrl;

    if (showBusyOverlay) {
      showBusy(true);
    }

    try {
      const payload = sanitizeNetworkMapData(await fetchJson(url));
      state.rawData = payload;
      state.rawNodeMap = buildNodeMap(payload.nodes);

      populateHostSelect(payload.nodes);

      const statusText = buildStatusText(payload.meta || {});
      setStatus(statusText || "Network map loaded.");

      if (redraw) {
        applyFiltersAndDraw({ showNoEdgesAlert });
      }

      return payload;
    } catch (error) {
      console.error(error);
      setStatus(`Failed to load network map: ${error.message || String(error)}`, true);
      throw error;
    } finally {
      if (showBusyOverlay) {
        showBusy(false);
      }
    }
  }

  function bindSummaryPanel() {
    const inputs = ["knm-sumFilterSrc", "knm-sumFilterDst", "knm-sumFilterPort"]
      .map((id) => getEl(id))
      .filter(Boolean);
    const closeButton = getEl("knm-closeSummary");
    const minimizeButton = getEl("knm-minimizeSummary");

    inputs.forEach((element) => {
      element.addEventListener("input", () => {
        if (typeof NM.updateSummaryDisplay === "function") {
          NM.updateSummaryDisplay();
        }
      });
    });

    if (closeButton) {
      closeButton.addEventListener("click", () => {
        const panel = getEl("knm-summary");

        clearFadedGraph();

        if (panel) {
          panel.hidden = true;
        }
      });
    }

    if (minimizeButton) {
      minimizeButton.addEventListener("click", () => {
        const filtersContainer = getEl("knm-summaryFilters");
        const content = getEl("knm-summaryContent");

        if (!filtersContainer || !content) {
          return;
        }

        const collapsed = filtersContainer.style.display === "none";

        filtersContainer.style.display = collapsed ? "block" : "none";
        content.style.display = collapsed ? "block" : "none";
        minimizeButton.textContent = collapsed ? "Collapse" : "Expand";
      });
    }
  }


  function bindControls() {
    const applyButton = getEl("knm-btnApply");
    const refreshButton = getEl("knm-btnRefreshData");
    const hostSelect = getEl("knm-hostSelect");
    const filterInputs = [
      "knm-filterSrc",
      "knm-filterDst",
      "knm-filterPort",
      "knm-filterIp",
      "knm-minSep",
      "knm-scaleX",
      "knm-scaleY",
    ]
      .map((id) => getEl(id))
      .filter(Boolean);

    if (applyButton) {
      applyButton.addEventListener("click", () => {
        applyFiltersAndDraw({ showNoEdgesAlert: true });
      });
    }

    if (refreshButton) {
      refreshButton.addEventListener("click", () => {
        setStatus("Refreshing data…");
        fetchNetworkMap({
          force: true,
          redraw: true,
          showBusyOverlay: true,
          showNoEdgesAlert: false,
        }).catch(() => {
          // status is already set in fetchNetworkMap
        });
      });
    }

    if (hostSelect) {
      hostSelect.addEventListener("change", () => {
        applyFiltersAndDraw({ showNoEdgesAlert: false });
      });
    }

    [getEl("knm-excludeNoisePorts"), getEl("knm-excludePub")]
      .filter(Boolean)
      .forEach((element) => {
        element.addEventListener("change", () => {
          applyFiltersAndDraw({ showNoEdgesAlert: false });
        });
      });

    filterInputs.forEach((element) => {
      element.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          applyFiltersAndDraw({ showNoEdgesAlert: true });
        }
      });
    });
  }

  function init() {
    const root = getEl("network-map-root");

    if (!root) {
      return;
    }

    if (state.initialized && state.root === root) {
      return;
    }

    ensureStateDefaults();
    state.root = root;

    buildLayout(root);
    bindSummaryPanel();
    bindControls();

    state.initialized = true;

    setStatus("Loading network map…");

    fetchNetworkMap({
      force: false,
      redraw: true,
      showBusyOverlay: true,
      showNoEdgesAlert: false,
    }).catch(() => {
      // fetchNetworkMap already updated the status text
    });
  }

  document.addEventListener("DOMContentLoaded", init);

  if (document.readyState !== "loading") {
    init();
  }
})(window);
