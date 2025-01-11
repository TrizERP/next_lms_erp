<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Graph</title>
    <script type="text/javascript" src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        @font-face {
        font-family: 'PJSemibold';
        src: url('/PlusJakartaSans-SemiBold.woff') format('woff'); 
        font-weight: normal;
        font-style: normal;
    }
        #mynetwork {
            width: 100%;
            height: 1000px;
            border: 1px solid lightgray;
            margin-top: 20px;
        }
        .node {
            stroke: #333;
            stroke-width: 1.5px;
        }
        .link {
            stroke: #999;
            stroke-opacity: 0.6;
        }
        .label {
            font-size: 12px;
            font-family: 'PJSemibold', sans-serif;
            font-weight: normal;
            text-anchor: middle;
            dominant-baseline: middle;
        }
        .highlighted {
            stroke: #FF6347;
            stroke-width: 3px;
        }
        .highlighted-link {
            stroke: #32CD32;
            stroke-width: 2px;
            stroke-opacity: 1;
        }
        h1{
            font-family: 'PJSemibold', sans-serif;
        }
    </style>
</head>
<body>

<h1>LMS Content Graph</h1>
<div id="mynetwork"></div> 

<div style="text-align: center; margin-top: 20px;">
    <button id="sync-data" style="padding: 10px 20px;">Sync Data from MySQL to Neo4j</button>
    <button id="refresh-graph" style="padding: 10px 20px;">Refresh Graph</button>
</div>

<br>
<script type="text/javascript">
    const studentId = 1;  

    function loadGraphData() {
        fetch('/graph-data')
            .then(response => response.json())
            .then(data => {
                console.log(data); 
                renderGraph(data);
            })
            .catch(error => console.error('Error fetching LMS graph data:', error));
    }

    function renderGraph(data) {
        const container = document.getElementById('mynetwork');
        const width = container.clientWidth;
        const height = container.clientHeight;

      
        d3.select("#mynetwork").html("");

       
        const svg = d3.select("#mynetwork").append("svg")
            .attr("width", width)
            .attr("height", height);

        const nodeMap = new Map();
        data.nodes.forEach(node => {
            nodeMap.set(node.id, node);
        });

        const links = data.edges.map(edge => {
            return {
                source: nodeMap.get(edge.from),
                target: nodeMap.get(edge.to),
                label: edge.label
            };
        });

        const simulation = d3.forceSimulation(data.nodes)
            .force("link", d3.forceLink(links).id(d => d.id).distance(150))
            .force("charge", d3.forceManyBody().strength(-300))
            .force("center", d3.forceCenter(width / 2, height / 2))
            .force("collide", d3.forceCollide().radius(30));  

        const link = svg.append("g")
            .selectAll(".link")
            .data(links)
            .enter().append("line")
            .attr("class", "link")
            .attr("stroke", "#999")
            .attr("stroke-width", 2);

        const linkLabels = svg.append("g")
            .selectAll(".link-label")
            .data(links)
            .enter().append("text")
            .attr("class", "label")
            .attr("x", d => (d.source.x + d.target.x) / 2)
            .attr("y", d => (d.source.y + d.target.y) / 2)
            .text(d => d.label);

        const node = svg.append("g")
            .selectAll(".node")
            .data(data.nodes)
            .enter().append("circle")
            .attr("class", "node")
            .attr("r", 20)
            .attr("fill", d => d.id === 144 ? "#32CD32" : "#ff0000") 
            .call(d3.drag()
                .on("start", dragstarted)
                .on("drag", dragged)
                .on("end", dragended))
            .on("click", highlightConnections); 

        const label = svg.append("g")
            .selectAll(".label")
            .data(data.nodes)
            .enter().append("text")
            .attr("class", "label")
            .attr("x", d => d.x)
            .attr("y", d => d.y)
            .text(d => d.label)
            .style("font-size", "12px")
            .style("text-anchor", "middle")
            .style("dominant-baseline", "middle");

        simulation.on("tick", () => {
            link
                .attr("x1", d => d.source.x)
                .attr("y1", d => d.source.y)
                .attr("x2", d => d.target.x)
                .attr("y2", d => d.target.y);

            linkLabels
                .attr("x", d => (d.source.x + d.target.x) / 2)
                .attr("y", d => (d.source.y + d.target.y) / 2);

            node
                .attr("cx", d => d.x)
                .attr("cy", d => d.y);

            label
                .attr("x", d => d.x)
                .attr("y", d => d.y);
        });

        //Highlighting of the connections of clicked nodes!!
        function highlightConnections(event, d) {
            const selectedNode = d;
            link.classed("highlighted-link", l => l.source === selectedNode || l.target === selectedNode);
            node.classed("highlighted", n => n === selectedNode);
        }
        //Dragging
        function dragstarted(event, d) {
            if (!event.active) simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        }

        function dragged(event, d) {
            d.fx = event.x;
            d.fy = event.y;
        }

        function dragended(event, d) {
            if (!event.active) simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        }
    }
    document.getElementById('refresh-graph').addEventListener('click', function () {
        loadGraphData();
    });

    document.getElementById('sync-data').addEventListener('click', function () {
        fetch('/sync-neo4j')
            .then(response => response.json())
            .then(data => {
                alert('Sync complete: ' + data.status);
                loadGraphData(); 
            })
            .catch(error => console.error('Error syncing data:', error));
    });

    window.onload = loadGraphData;
</script>

</body>
</html>