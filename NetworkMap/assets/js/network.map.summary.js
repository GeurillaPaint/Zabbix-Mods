// Network Map - node summary
(function (global) {
  "use strict";

  const NM = (global.NetworkMap = global.NetworkMap || {});
  const filters = NM.filters;
  const state =
    (global.KNMState =
      global.KNMState ||
      {
        rawData: null,
        currentGraph: { nodes: [], edges: [] },
        cy: null,
        summaryData: { incoming: [], outgoing: [] },
      });

  function endpointLabel(edge, side) {
    if (!edge || !edge.data) {
      return "";
    }

    if (side === "source") {
      return edge.data.sourceLabel || edge.data.source || "";
    }

    return edge.data.targetLabel || edge.data.target || "";
  }

  function updateSummaryDisplay() {
    const sumFsrc = document.getElementById("knm-sumFilterSrc");
    const sumFdst = document.getElementById("knm-sumFilterDst");
    const sumFport = document.getElementById("knm-sumFilterPort");
    const summaryContent = document.getElementById("knm-summaryContent");

    if (!sumFsrc || !sumFdst || !sumFport || !summaryContent) {
      return;
    }

    const fs = filters.parseSumTokens(sumFsrc.value);
    const fd = filters.parseSumTokens(sumFdst.value);
    const fp = filters.parseSumTokens(sumFport.value);

    const lines = [];
    let matched = 0;

    function appendSection(title, entries) {
      const sectionLines = [];

      entries.forEach((entry) => {
        const portNum = filters.extractPort(entry.servicePort ?? entry.port);

        if (
          filters.matchTokens(entry.src, fs) &&
          filters.matchTokens(entry.dst, fd) &&
          filters.matchPortTokens(portNum, fp)
        ) {
          sectionLines.push(`${entry.src}\t${entry.dst}\t${entry.port}`);
          matched += 1;
        }
      });

      if (sectionLines.length) {
        lines.push(title);
        lines.push(...sectionLines);
        lines.push("");
      }
    }

    appendSection("Incoming:", state.summaryData.incoming || []);
    appendSection("Outgoing:", state.summaryData.outgoing || []);

    if (matched === 0) {
      lines.length = 0;
      lines.push("No traffic matched the summary filters.");
    } else if (lines.length && lines[lines.length - 1] === "") {
      lines.pop();
    }

    summaryContent.textContent = lines.join("\n");
  }

  function showSummary(node) {
    const summaryBox = document.getElementById("knm-summary");
    const summaryTitle = document.getElementById("knm-summaryTitle");

    if (!summaryBox || !summaryTitle || !state.currentGraph || !state.cy) {
      return;
    }

    state.cy.elements().not(node.closedNeighborhood()).addClass("faded");

    const drawnEdges = state.currentGraph.edges || [];
    const incoming = drawnEdges.filter((edge) => edge.data.target === node.id());
    const outgoing = drawnEdges.filter((edge) => edge.data.source === node.id());

    state.summaryData = {
      incoming: incoming.map((edge) => ({
        src: endpointLabel(edge, "source"),
        dst: endpointLabel(edge, "target"),
        port: edge.data.label,
        servicePort: edge.data.servicePort,
      })),
      outgoing: outgoing.map((edge) => ({
        src: endpointLabel(edge, "source"),
        dst: endpointLabel(edge, "target"),
        port: edge.data.label,
        servicePort: edge.data.servicePort,
      })),
    };

    summaryTitle.textContent = `Traffic for ${node.data("label")}`;

    const sumFilterSrc = document.getElementById("knm-sumFilterSrc");
    const sumFilterDst = document.getElementById("knm-sumFilterDst");
    const sumFilterPort = document.getElementById("knm-sumFilterPort");
    const summaryFilters = document.getElementById("knm-summaryFilters");
    const summaryContent = document.getElementById("knm-summaryContent");
    const minimizeSummary = document.getElementById("knm-minimizeSummary");

    if (sumFilterSrc) {
      sumFilterSrc.value = "";
    }
    if (sumFilterDst) {
      sumFilterDst.value = "";
    }
    if (sumFilterPort) {
      sumFilterPort.value = "";
    }
    if (summaryFilters) {
      summaryFilters.style.display = "block";
    }
    if (summaryContent) {
      summaryContent.style.display = "block";
    }
    if (minimizeSummary) {
      minimizeSummary.textContent = "Collapse";
    }

    summaryBox.hidden = false;
    updateSummaryDisplay();
  }

  NM.updateSummaryDisplay = updateSummaryDisplay;
  NM.showSummary = showSummary;
})(window);
