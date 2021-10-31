/**
 *  Copyright 2020 Aston University
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import * as d3 from "d3";

function generateClipID(d) {
    let baseText = (d.parent ? d.parent.data.name + "_" : "") + d.data.name;
    return baseText.replace(/\W/g, '');
}

function CohortChart(chartSelector, modalSelector, svgWidth) {
    this.chartSelector = chartSelector;
    this.modalSelector = modalSelector;
    this.svgWidth  = svgWidth;

    /*
     * The height of the chart is automatically computed from the aspect ratio of the viewport.
     */
    const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
    this.svgHeight = this.svgWidth * (vh/vw);

    // CONFIGURATION AREA
    let margin = {top: 10, right: 10, bottom: 10, left: 10};

    // Characters are from FontAwesome
    let testResults = {
        passed: "\uf058",
        failed: "\uf057",
        errored: "\uf071",
        skipped: "\uf05e",
        missing: "\uf059"
    };

    // DRAWING PART
    let width = this.svgWidth - margin.left - margin.right;
    let height = this.svgHeight - margin.top - margin.bottom;

    this.draw = function (data) {
        d3.select(chartSelector)
            .append("svg")
            .attr("viewBox", `0 0 ${this.svgWidth} ${this.svgHeight}`)
            .append("g")
            .attr("transform", `translate(${margin.left} ${margin.top})`);

        let root = d3.hierarchy(data).sum(d => d.value);

        d3.treemap()
            .size([width, height])
            .paddingOuter(10)
            .paddingTop(20)
            (root);

        let nodes = d3.select(`${chartSelector} svg`)
            .selectAll('g')
            .data(root.descendants().filter(d => d.depth < 3 || (d.depth === 3 && d.data.value > 0)))
            .enter()
            .append("g")
            .attr("transform", d => `translate(${d.x0}, ${d.y0})`)
            .each(function (d) {
                let group = d3.select(this);
                if (d.data.name in testResults) {
                    let ancestors = d.ancestors();
                    group.classed('result-' + d.data.name, true)
                         .attr('data-result', d.data.name)
                         .attr('data-test', d.parent.data.name)
                         .attr('data-class', d.parent.parent.data.name);
                }
            });

        nodes.append("rect")
            .attr("width", d => d.x1 - d.x0)
            .attr("height", d => d.y1 - d.y0)
            .filter(d => d.depth === 3)
            .attr("data-toggle", "modal")
            .attr("data-target", modalSelector)
            .append("title")
            .text(d => d.parent.parent.data.name + "::" + d.parent.data.name + "::" + d.data.name + " = " + d.data.value);

        nodes.append("clipPath")
            .attr("id", d => "clip" + generateClipID(d))
            .append("rect")
            .attr("width", d => d.x1 - d.x0)
            .attr("height", d => d.y1 - d.y0);

        nodes.filter(d => d.depth === 3)
            .append("text")
            .attr("dx", d => (d.x1 - d.x0) / 2)
            .attr("dy", d => (d.y1 - d.y0) / 2 + 10)
            .attr("data-toggle", "modal")
            .attr("data-target", modalSelector)
            .classed("type-label", true)
            .classed("fa", true)
            .text(d => testResults[d.data.name]);

        nodes.filter(d => d.depth < 3)
            .append('text')
            .attr('dx', 4)
            .attr('dy', 14)
            .attr('clip-path', d => 'url(#clip' + generateClipID(d) + ')')
            .classed("test-label", true)
            .text(d => {
                if (d.depth === 1) {
                    let nameParts = d.data.name.split('.');
                    return nameParts[nameParts.length - 1];
                } else {
                    return d.data.name;
                }
            })
            .style("fill", d => d.depth === 1 ? 'black' : 'white');
    };
}

export default CohortChart;
