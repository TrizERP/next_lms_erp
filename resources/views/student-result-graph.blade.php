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
    
    /* Add proper margin to main content */
    .main-content {
        margin-left: 200px; /* Adjust based on sidebar width */
        margin-top: 70px; /* Adjust based on header height */
        padding: 20px;
        width: calc(100% - 250px); /* Adjust based on sidebar width */
        box-sizing: border-box;
    }
    
    /* Make container-fluid adjust properly */
    .container-fluid {
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        margin-right: auto;
        margin-left: auto;
    }
    
    /* Ensure the card doesn't overflow */
    .card {
        margin-top: 20px;
        overflow: visible;
    }
    
    /* Controls styling */
    .controls {
        margin: 20px 0;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .controls button {
        padding: 8px 16px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .controls button:hover {
        background: #0056b3;
    }
</style>

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
