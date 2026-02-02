@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<!-- MAIN CONTENT START -->
<div class="main-content">
    <div class="container-fluid">

        <div class="page-title">
            <h2>Student Result Graph</h2>
            <nav>
                <a href="{{ url('/') }}">Home</a> / Student Results
            </nav>
        </div>

        <div class="card">
            <div class="card-body">
                <div id="graph"></div>
            </div>
        </div>

    </div>
</div>
<!-- MAIN CONTENT END -->

@include('includes.footerJs')
@include('includes.footer')

<!-- Vis Network CDN -->
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>

<style>
    #graph {
        width: 100%;
        height: 600px;
        border: 1px solid #ddd;
    }
</style>
<div class="controls">
    <button onclick="layoutGrid()">Grid Layout</button>
    <button onclick="layoutCircle()">Circle Layout</button>
    <button onclick="layoutRandom()">Random Layout</button>
    <button onclick="zoomIn()">Zoom In</button>
    <button onclick="zoomOut()">Zoom Out</button>
    <button onclick="resetView()">Reset View</button>
    <button onclick="exportPNG()">Export as PNG</button>
</div>
<script>
    const stuId = 95551;

    fetch(`/api/student-results/${stuId}/graph`)
        .then(res => res.json())
        .then(data => {

            const nodes = data.nodes.map(node => ({
                id: node.id,
                label: node.labels[0],
                title: Object.entries(node.properties)
                    .map(([k,v]) => `${k}: ${v}`)
                    .join('\n')
            }));

            const edges = data.relationships.map(rel => ({
                from: rel.startNode,
                to: rel.endNode,
                label: rel.type,
                arrows: 'to'
            }));

            const container = document.getElementById('graph');
            const graphData = {
                nodes: new vis.DataSet(nodes),
                edges: new vis.DataSet(edges)
            };

            const options = {
                nodes: {
                    shape: 'dot',
                    size: 16
                },
                physics: {
                    stabilization: true
                }
            };

            new vis.Network(container, graphData, options);
        })
        .catch(err => console.error(err));
</script>
