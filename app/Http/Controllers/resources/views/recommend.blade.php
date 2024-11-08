<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Knowledge Graph</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        #mynetwork{
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
        edges:{
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
                nodeSpacing:500,
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
            const chapterNodeId = `chapter-${chapterId}`;
            questionNodes.add({
                id: chapterNodeId,
                label: `ChapterId-${chapterId}`,
                color: '#FF5733'
            });
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
        questionNodes.add(data.nodes);
        displayQuestionGraph();
        })
        .catch(error => console.error('Error fetching question data for particular chapter:', error));
}
function loadPersonalizedLearningPath(studentId) {
    fetch(`/get-personalized-learning-path/${studentId}`)
        .then(response => response.json())
        .then(data => {
            console.log(data.recommendations);
        })
        .catch(error => console.error('Error fetching personalized learning path for particular student:', error));
}
    mainNetwork.on('click', function (params) {
        if (params.nodes.length > 0) {
            const nodeId = params.nodes[0];
            console.log(nodeId);
            fetch(`/get-related-data/${nodeId}`)
                .then(response => response.json())
                .then(data => {
                    nodes.add(data.nodes);
                    edges.add(data.edges);

                    if (data.chapterIds && data.chapterIds.length > 0) {
                    data.chapterIds.forEach(chapterId => {
                        console.log('Chapter ID:', chapterId);
                        loadQuestionGraphData(chapterId);
                    });
                }
                })
                .catch(error => console.error('Error fetching related data:', error));
        }
    });
    window.onload = function() {
    loadMainGraphData();
    loadPersonalizedLearningPath(1);
};
</script>

</body>
</html>
