<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Graph</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        #mynetwork, #learningpathnetwork {
            width: 100%;
            height: 600px;
            border: 1px solid lightgray;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<h1>LMS Content Graph</h1>
<div id="mynetwork"></div> <!-- Div for rendering the LMS graph -->

<div style="text-align: center; margin-top: 20px;">
    <button id="sync-data" style="padding: 10px 20px;">Sync Data from MySQL to Neo4j</button>
    <button id="refresh-graph" style="padding: 10px 20px;">Refresh Graph</button>
</div>
<br>
<br>
<h1>Personalized Learning Path</h1>
<button id="get-recommendations" style="padding: 10px 20px;">Get Recommendations</button>

<div id="recommendations"></div>
<br>
<br>
<script type="text/javascript">
    document.getElementById('get-recommendations').addEventListener('click', function() {
        fetch('/recommendations')
            .then(response => response.json())
            .then(data => {
                let recommendationDiv = document.getElementById('recommendations');
                recommendationDiv.innerHTML = '';  // Clear previous recommendations

                data.forEach(function(rec) {
                    let p = document.createElement('p');
                    p.innerHTML = `Chapter: ${rec.label}, Difficulty: ${rec.difficulty}, Popularity: ${rec.popularity}`;
                    recommendationDiv.appendChild(p);
                });
            })
            .catch(error => console.error('Error fetching recommendations:', error));
    });
</script>
<br>
<br>
<h1>Personalized Learning Path</h1>
<div id="learningpathnetwork"></div> <!-- Div for rendering the learning path graph -->

<script type="text/javascript">
    // Initialize the LMS graph with empty datasets for nodes and edges
    var nodes = new vis.DataSet([]);
    var edges = new vis.DataSet([]);

    // Set up the LMS graph container and network visualization
    var container = document.getElementById('mynetwork');
    var data = {
        nodes: nodes,
        edges: edges
    };
    var options = {};
    var network = new vis.Network(container, data, options);

    // Sync MySQL data to Neo4j
    document.getElementById('sync-data').addEventListener('click', function () {
        fetch('/sync-neo4j')
            .then(response => response.json())
            .then(data => {
                alert('Sync complete: ' + data.status);
                loadGraphData(); // Optionally refresh the LMS graph data after sync
            })
            .catch(error => console.error('Error syncing data:', error));
    });

    // Fetch LMS graph data from your API
    function loadGraphData() {
        fetch('/graph-data')
            .then(response => response.json())
            .then(data => {
                nodes.clear();  // Clear existing nodes and edges
                edges.clear();
                nodes.add(data.nodes);  // Add new nodes
                edges.add(data.edges);  // Add new edges
            })
            .catch(error => console.error('Error fetching LMS graph data:', error));
    }

    // Load LMS graph data on page load
    window.onload = loadGraphData;

    // Refresh the LMS graph when the refresh button is clicked
    document.getElementById('refresh-graph').addEventListener('click', function () {
        loadGraphData();
    });
</script>

<script type="text/javascript">
    // Initialize the personalized learning path graph with empty datasets for nodes and edges
    var pathNodes = new vis.DataSet([]);
    var pathEdges = new vis.DataSet([]);

    // Set up the personalized learning path graph container and network visualization
    var pathContainer = document.getElementById('learningpathnetwork');
    var pathData = {
        nodes: pathNodes,
        edges: pathEdges
    };
    var pathOptions = {
        edges: {
            arrows: { to: { enabled: true } }, // Enable arrows at the end of edges
            color: '#000000'
        },
        nodes: {
            shape: 'dot',
            size: 20,
            font: { size: 16, color: '#000' },
            borderWidth: 2
        }
    };
    var pathNetwork = new vis.Network(pathContainer, pathData, pathOptions);

    // Fetch personalized learning path data from your API
    function loadLearningPathData() {
        fetch('/graph-data-learning-path')
            .then(response => response.json())
            .then(data => {
                pathNodes.clear();  // Clear existing nodes and edges
                pathEdges.clear();
                pathNodes.add(data.nodes);  // Add new nodes
                pathEdges.add(data.edges);  // Add new edges
            })
            .catch(error => console.error('Error fetching learning path data:', error));
    }

    // Load the learning path graph data on page load
    window.onload = function () {
        loadGraphData();  // Load LMS content graph
        loadLearningPathData();  // Load personalized learning path
    };
</script>

</body>
</html>
