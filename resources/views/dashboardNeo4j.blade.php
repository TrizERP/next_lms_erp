<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Knowledge Graph</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        #mynetwork {
            width: 100%;
            height: 600px;
            border: 1px solid lightgray;
            margin-top: 20px;
        }
        #questionNetwork {
            width: 100%;
            height: 600px;
            border: 1px solid white;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div id="mynetwork"></div>
<div id="questionNetwork"></div>

<script type="text/javascript">
    var nodes = new vis.DataSet([]);
    var edges = new vis.DataSet([]);
    var questionNodes = new vis.DataSet([]);
    var questionEdges = new vis.DataSet([]);

    var mainContainer = document.getElementById('mynetwork');
    var mainData = { nodes: nodes, edges: edges };
    var questionContainer = document.getElementById('questionNetwork');
    var questionData = { nodes: questionNodes, edges: questionEdges };

    var options = {
        interaction: { hover: true },
        layout: {
            hierarchical: {
                enabled: true,
                levelSeparation: 150,
                nodeSpacing: 800,
                treeSpacing: 10000,
                direction: 'UD',
                sortMethod: 'directed'
            }
        },
        edges: {
            smooth: { type: 'continuous', roundness: 0.5 },
            arrows: { to: { enabled: true, scaleFactor: 1.5 } },
            color: { inherit: 'from' }
        },
        physics: false
    };

    var optionsNew = {
        interaction: { hover: true },
        layout: {
            hierarchical: {
                enabled: true,
                levelSeparation: 150,
                nodeSpacing: 500,
                treeSpacing: 1000,
                direction: 'UD',
                sortMethod: 'directed'
            }
        },
        edges: {
            smooth: { type: 'continuous', roundness: 0.5 },
            arrows: { to: { enabled: true, scaleFactor: 1.5 } },
            color: { inherit: 'from' }
        },
        physics: false
    };

    var mainNetwork = new vis.Network(mainContainer, mainData, options);
    var questionNetwork = new vis.Network(questionContainer, questionData, optionsNew);

    // Function to load the initial main graph data (students)
    function loadMainGraphData() {
        fetch('/get-students')
            .then(response => response.json())
            .then(data => {
                nodes.clear();
                nodes.add(data.nodes);
                mainNetwork.setData(mainData);  // Update network data after fetching
                console.log("Main graph data loaded:", data);
            })
            .catch(error => console.error('Error fetching student data:', error));
    }

    // Function to load the initial Questions graph data (chapters)
    function loadQuestionGraphData(chapterId) {
        fetch(`/get-questions-for-chapter/${chapterId}`)
            .then(response => response.json())
            .then(data => {
                questionNodes.add(data.nodes);
                questionEdges.add(data.edges);

                // Add chapter node and connect it to the questions
                const chapterNodeId = `chapter-${chapterId}`;
                questionNodes.add({
                    id: chapterNodeId,
                    label: `ChapterId-${chapterId}`,
                    color: '#FF5733'
                });

                // Chain questions in sequence
                const questionIds = data.nodes.map(node => node.id);
                if (questionIds.length > 0) {
                    questionEdges.add({
                        from: chapterNodeId,
                        to: questionIds[0],
                        arrows: "to",
                        label: "CONTAINS"
                    });

                    for (let i = 0; i < questionIds.length - 1; i++) {
                        questionEdges.add({
                            from: questionIds[i],
                            to: questionIds[i + 1],
                            arrows: "to",
                            label: "NEXT"
                        });
                    }
                }

                questionNetwork.setData(questionData);  // Update question network data
                console.log("Question graph data loaded for chapter:", chapterId, data);
            })
            .catch(error => console.error('Error fetching question data for particular chapter:', error));
    }

    // Load personalized learning path for debugging
    function loadPersonalizedLearningPath(studentId) {
        console.log("Personalized learning path:");
        // fetch(`/get-personalized-learning-path/${studentId}`)
        //     .then(response => response.json())
        //     .then(data => {
        //         console.log("Personalized learning path:", data.recommendations);
        //     })
        //     .catch(error => console.error('Error fetching personalized learning path for particular student:', error));
    }

    // Click event on the main network
    mainNetwork.on('click', function (params) {
    if (params.nodes.length > 0) {
        const nodeId = params.nodes[0];
        console.log('Node clicked:', nodeId);
        fetch(`/get-related-data/${nodeId}`)
            .then(response => response.json())
            .then(data => {
                data.nodes.forEach(node => {
                    if (!nodes.get(node.id)) {  // Check if node ID already exists
                        nodes.add(node);
                    } else {
                        console.log(`Node with id ${node.id} already exists.`);
                    }
                });

                // Add edges with strong duplicate checks
                data.edges.forEach(edge => {
                    const existingEdge = edges.get({
                        filter: existingEdge => existingEdge.from === edge.from && existingEdge.to === edge.to
                    });

                    if (existingEdge.length === 0) {  // Only add if no duplicate edge exists
                        edges.add(edge);
                    } else {
                        console.log(`Edge from ${edge.from} to ${edge.to} already exists.`);
                    }
                });

                mainNetwork.setData(mainData); 
                if (data.chapterIds && data.chapterIds.length > 0) {
                    data.chapterIds.forEach(chapterId => {
                        loadQuestionGraphData(chapterId);
                    });
                }
            })
            .catch(error => console.error('Error fetching related data:', error));
            }
        });

    // Initialize the graph on page load
    window.onload = function() {
        loadMainGraphData();
        loadPersonalizedLearningPath(1);  // Example student ID for testing
    };
</script>

</body>
</html>
