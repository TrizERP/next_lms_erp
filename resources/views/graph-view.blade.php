
@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">

<div class="graph-container">
    <div id="cy" style="width: 100%; height: 600px; border: 1px solid #ddd;"></div>
</div>

<style>
    .graph-container {
        margin: 20px 0;
        padding: 15px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    #cy {
        min-height: 500px;
        background: #f8f9fa;
    }
    
    .node-label {
        font-size: 10px;
        color: #333;
    }
    
    .controls {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .controls button {
        padding: 8px 16px;
        background: #4f46e5;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .controls button:hover {
        background: #4338ca;
    }
    
    .node-info {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        border: 1px solid #ddd;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        max-width: 400px;
        max-height: 300px;
        overflow-y: auto;
        display: none;
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

<div id="nodeInfo" class="node-info"></div>
</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.23.0/cytoscape.min.js"></script>
<script>
    // Define the JSON data from Laravel
    const graphData = @json($data ?? [
        "rootNode" => null,
        "nodes" => [],
        "relationships" => []
    ]);

    // Initialize Cytoscape
    document.addEventListener('DOMContentLoaded', function() {
        initGraph();
    });

    let cy;

    function initGraph() {
        // Prepare nodes
        const elements = [];

        // Add nodes (skip JobRole nodes initially)
        graphData.nodes.forEach(node => {
            const label = node.labels[0] || 'Unknown';
            if (label !== 'JobRole') {
                let nodeStyle = getNodeStyle(label);

                elements.push({
                    data: {
                        id: 'node_' + node.id,
                        label: node.properties.title || node.properties.name || label,
                        type: label,
                        properties: node.properties,
                        originalData: node
                    },
                    style: nodeStyle
                });
            }
        });

        // Add edges (relationships) (skip IS_IN_DEPT edges initially)
        graphData.relationships.forEach(rel => {
            if (rel.type !== 'IS_IN_DEPT') {
                elements.push({
                    data: {
                        id: 'edge_' + rel.id,
                        source: 'node_' + rel.startNode,
                        target: 'node_' + rel.endNode,
                        label: rel.type,
                        type: rel.type
                    }
                });
            }
        });

        // Initialize Cytoscape
        cy = cytoscape({
            container: document.getElementById('cy'),
            elements: elements,
            style: [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(label)',
                        'text-valign': 'center',
                        'text-halign': 'center',
                        'font-size': '14px',
                        'font-weight': 'bold',
                        'color': '#333',
                        'text-wrap': 'wrap',
                        'text-max-width': '80px',
                        'text-overflow-wrap': 'anywhere',
                        'padding': '10px'
                    }
                },
                {
                    selector: 'node[type="JobRole"]',
                    style: {
                        'background-color': '#4f46e5',
                        'shape': 'round-rectangle',
                        'width': '100px',
                        'height': '60px'
                    }
                },
                {
                    selector: 'node[type="Department"]',
                    style: {
                        'background-color': '#10b981',
                        'shape': 'ellipse',
                        'width': '80px',
                        'height': '80px'
                    }
                },
                {
                    selector: 'node[type="Organization"]',
                    style: {
                        'background-color': '#f59e0b',
                        'shape': 'diamond',
                        'width': '90px',
                        'height': '90px'
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        'width': 2,
                        'line-color': '#94a3b8',
                        'target-arrow-color': '#94a3b8',
                        'target-arrow-shape': 'triangle',
                        'curve-style': 'bezier',
                        'label': 'data(label)',
                        'font-size': '10px',
                        'color': '#64748b',
                        'text-rotation': 'autorotate'
                    }
                },
                {
                    selector: 'edge[type="BELONGS_TO_ORG"]',
                    style: {
                        'line-color': '#f59e0b',
                        'target-arrow-color': '#f59e0b'
                    }
                },
                {
                    selector: 'edge[type="IS_IN_DEPT"]',
                    style: {
                        'line-color': '#10b981',
                        'target-arrow-color': '#10b981'
                    }
                },
                {
                    selector: 'edge[type="HAS_PARENT"]',
                    style: {
                        'line-color': '#8b5cf6',
                        'target-arrow-color': '#8b5cf6',
                        'line-style': 'dashed'
                    }
                }
            ],
            layout: {
                name: 'cose',
                animate: true,
                animationDuration: 1000,
                fit: true,
                padding: 30
            }
        });

        // Add double-click event for Department nodes to show job roles
        cy.on('dbltap', 'node[type="Department"]', function(evt) {
            const deptNode = evt.target;
            const deptId = deptNode.data('id').replace('node_', '');

            // Find and add connected job roles and edges
            graphData.relationships.forEach(rel => {
                if (rel.endNode == deptId && rel.type === 'IS_IN_DEPT') {
                    // Find the job role node
                    const jobNode = graphData.nodes.find(n => n.id == rel.startNode);
                    if (jobNode && !cy.getElementById('node_' + jobNode.id).length) {
                        // Add the job role node
                        cy.add({
                            data: {
                                id: 'node_' + jobNode.id,
                                label: jobNode.properties.title || jobNode.properties.name || jobNode.labels[0],
                                type: jobNode.labels[0],
                                properties: jobNode.properties,
                                originalData: jobNode
                            },
                            style: getNodeStyle(jobNode.labels[0])
                        });

                        // Add the edge
                        cy.add({
                            data: {
                                id: 'edge_' + rel.id,
                                source: 'node_' + rel.startNode,
                                target: 'node_' + rel.endNode,
                                label: rel.type,
                                type: rel.type
                            }
                        });
                    }
                }
            });

            // Re-run layout to position new elements
            cy.layout({ name: 'cose', animate: true, fit: true }).run();
        });

        // Add click event for nodes
        cy.on('tap', 'node', function(evt) {
            const node = evt.target;
            showNodeInfo(node.data());
        });

        // Add hover effects
        cy.on('mouseover', 'node', function(evt) {
            const node = evt.target;
            node.style({
                'border-width': '3px',
                'border-color': '#f59e0b'
            });
        });

        cy.on('mouseout', 'node', function(evt) {
            const node = evt.target;
            node.style({
                'border-width': '0px'
            });
        });
    }

    function getNodeStyle(label) {
        const styles = {
            'JobRole': {
                'backgroundColor': '#4f46e5',
                'color': 'white',
                'shape': 'round-rectangle'
            },
            'Department': {
                'backgroundColor': '#10b981',
                'color': 'white',
                'shape': 'ellipse'
            },
            'Organization': {
                'backgroundColor': '#f59e0b',
                'color': 'white',
                'shape': 'diamond'
            }
        };
        
        return styles[label] || {
            'backgroundColor': '#94a3b8',
            'color': 'white',
            'shape': 'rectangle'
        };
    }

    function showNodeInfo(nodeData) {
        const infoDiv = document.getElementById('nodeInfo');
        const properties = nodeData.properties;
        
        let html = `<h4>${nodeData.label}</h4>`;
        html += `<p><strong>Type:</strong> ${nodeData.type}</p>`;
        
        if (properties) {
            html += '<h5>Properties:</h5><ul>';
            for (const [key, value] of Object.entries(properties)) {
                if (value && typeof value === 'string' && value.length > 50) {
                    html += `<li><strong>${key}:</strong> ${value.substring(0, 50)}...</li>`;
                } else {
                    html += `<li><strong>${key}:</strong> ${value}</li>`;
                }
            }
            html += '</ul>';
        }
        
        infoDiv.innerHTML = html;
        infoDiv.style.display = 'block';
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            infoDiv.style.display = 'none';
        }, 10000);
    }

    // Layout functions
    function layoutGrid() {
        cy.layout({ name: 'grid', animate: true }).run();
    }

    function layoutCircle() {
        cy.layout({ name: 'circle', animate: true }).run();
    }

    function layoutRandom() {
        cy.layout({ name: 'random', animate: true }).run();
    }

    function zoomIn() {
        cy.zoom(cy.zoom() * 1.2);
    }

    function zoomOut() {
        cy.zoom(cy.zoom() * 0.8);
    }

    function resetView() {
        cy.fit();
    }

    function exportPNG() {
        const png64 = cy.png({ scale: 2, bg: 'white' });
        const link = document.createElement('a');
        link.download = 'neo4j-graph-export.png';
        link.href = png64;
        link.click();
    }

    // Close node info when clicking outside
    document.addEventListener('click', function(event) {
        const infoDiv = document.getElementById('nodeInfo');
        if (!infoDiv.contains(event.target)) {
            infoDiv.style.display = 'none';
        }
    });
</script>
@include('includes.footerJs')
@include('includes.footer')