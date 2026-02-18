<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personalized Learning Path</title>
    <script type="text/javascript" src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        @font-face {
        font-family: 'PJSemibold';
        src: url('/PlusJakartaSans-SemiBold.woff') format('woff'); 
        font-weight: normal;
        font-style: normal;
    }
    body{
        font-family: 'PJSemibold';
    }
        #learningpathnetwork {
            width: 100%;
            height: 1000px;
            border: 1px solid lightgray;
            margin-top: 20px;
        }
        .node {
            stroke: #333;
            stroke-width: 1.5px;
            cursor: pointer;
        }
        .link {
            stroke: #999;
            stroke-opacity: 0.6;
        }
        .highlighted {
            stroke: #FFD700;
            stroke-width: 3;
        }
        .highlighted-node {
            fill: #FFD700;
        }
        .label {
            font-size: 15px;
            text-anchor: middle;
            dominant-baseline: middle;
        }
        .edge-label {
            font-size: 10px;
            text-anchor: middle;
            fill: #555;
        }
    </style>
</head>
<body>

<h1>Personalized Learning Path</h1>
<div id="learningpathnetwork"></div>
<br />
<br />
<div class="justify-center items-center text-center mb-3">
<button id="get-recommendations" type="button" class="btn btn-outline-success">Get Recommendations</button>
</div>
<div id="recommendations" class="text-center"></div>
<script type="text/javascript">
    const studentId = 1;
    function loadLearningPathData() {
        fetch(`/graph-data-learning-path`)
            .then(response => response.json())
            .then(data => {
                console.log(data);
                renderGraph(data);
            })
            .catch(error => console.error('Error fetching learning path data:', error));
    }

    function renderGraph(data) {
        const container = document.getElementById('learningpathnetwork');
        const width = container.clientWidth;
        const height = container.clientHeight;

        d3.select("#learningpathnetwork").html("");

        const svg = d3.select("#learningpathnetwork").append("svg")
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
        const edgeLabels = svg.append("g")
            .selectAll(".edge-label")
            .data(links)
            .enter().append("text")
            .attr("class", "edge-label")
            .attr("x", d => (d.source.x + d.target.x) / 2)
            .attr("y", d => (d.source.y + d.target.y) / 2)
            .text(d => d.label)
            .style("font-size", "10px")
            .style("text-anchor", "middle")
            .style("dominant-baseline", "middle");

        const node = svg.append("g")
            .selectAll(".node")
            .data(data.nodes)
            .enter().append("circle")
            .attr("class", "node")
            .attr("r", 20)
            .attr("fill", function(d) {
                if (d.id === 0) {
                    return "#32CD32"; 
                } else if (d.id === 16 || d.id === 145) {
                    return "#FFD700";
                } else if (d.id === 154 || d.id === 152 || d.id === 150 || d.id === 146 ||d.id === 148) {
                    return "#4682B4";
                } else {
                    return "#FF6347"; 
                }
            })
            .call(d3.drag()
                .on("start", dragstarted)
                .on("drag", dragged)
                .on("end", dragended))
            .on("click", nodeClicked); 
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

            node
                .attr("cx", d => d.x)
                .attr("cy", d => d.y);

            label
                .attr("x", d => d.x)
                .attr("y", d => d.y);

            edgeLabels
                .attr("x", d => (d.source.x + d.target.x) / 2)
                .attr("y", d => (d.source.y + d.target.y) / 2);
        });

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

        function nodeClicked(event, d) {
            
            svg.selectAll(".highlighted").classed("highlighted", false);
            svg.selectAll(".highlighted-node").classed("highlighted-node", false);

            d3.select(this).classed("highlighted-node", true);

            svg.selectAll(".link")
                .filter(link => link.source === d || link.target === d)
                .classed("highlighted", true);

            svg.selectAll(".node")
                .filter(node => node === d || links.some(link => (link.source === node && link.target === d) || (link.target === node && link.source === d)))
                .classed("highlighted-node", true);
        }
    }

    window.onload = loadLearningPathData;
</script>
<script type="text/javascript">
    document.getElementById('get-recommendations').addEventListener('click', function() {
        fetch(`/recommendations?studentId=${studentId}`)
            .then(response => response.json())
            .then(data => {
                let recommendationDiv = document.getElementById('recommendations');
                recommendationDiv.innerHTML = ''; 

                data.forEach(function(rec) {
                    let p = document.createElement('p');
                    p.innerHTML = `Chapter: ${rec.label}, Difficulty: ${rec.difficulty}, Popularity: ${rec.popularity}`;
                    recommendationDiv.appendChild(p);
                });
            })
            .catch(error => console.error('Error fetching recommendations:', error));
    });
</script>
</body>
</html>




<!-- NODE SCHEMA: 
{
  "node_id": "Math-Algebra",
  "domain": "Mathematics",
  "difficulty": "Medium",
  "prerequisites": ["Math-Basics"],
  "resources": [
      "https://example.com/algebra-tutorial",
      "https://example.com/algebra-video"
  ],
  "progress": {
      "score": 80,
      "status": "Proficient"
  }
} -->
