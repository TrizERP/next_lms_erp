{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<style>
.highcharts-figure,
.highcharts-data-table table {
    min-width: 320px;
    max-width: 800px;
    margin: 1em auto;
}

.highcharts-data-table table {
    font-family: Verdana, sans-serif;
    border-collapse: collapse;
    border: 1px solid #ebebeb;
    margin: 10px auto;
    text-align: center;
    width: 100%;
    max-width: 500px;
}

.highcharts-data-table caption {
    padding: 1em 0;
    font-size: 1.2em;
    color: #555;
}

.highcharts-data-table th {
    font-weight: 600;
    padding: 0.5em;
}

.highcharts-data-table td,
.highcharts-data-table th,
.highcharts-data-table caption {
    padding: 0.5em;
}

.highcharts-data-table thead tr,
.highcharts-data-table tr:nth-child(even) {
    background: #f8f8f8;
}

.highcharts-data-table tr:hover {
    background: #f1f7ff;
}
.dotline {
  /*border:none;*/
  /*border-top:5px dotted #f00; */
  color: red;
  background-color:#fff;
  height:5px;
  font-weight: bold;
  font-size: 50px;
}
.dashline {
  /*border:none;*/
  /*border-top:5px dashed blue;*/
  color: blue;
  background-color:#fff;
  height:1px;
  font-weight: bold;
  font-size: 50px;
}
.dashdotline {
  /*border:none;*/
  /*border-top:5px dashed blue;*/
  color: green;
  background-color:#fff;
  height:1px;
  font-weight: bold;
  font-size: 50px;
}
</style>

<div class="content-main flex-fill">
    <!-- <p class="highcharts-description">


    </p> -->
    <figure class="highcharts-figure">
        <div id="container"></div>
    </figure>

    <center>
    <table border="1" cellspacing="10" cellpadding="10" width="50%">
        <tr>
            <td width="30%" class="dotline">
                . . . . . . . . .
            </td>
            <td width="70%">
                Red dot line indicated chapters
            </td>
        </tr>
        <tr>
            <td width="30%" class="dashline">
                ——————
            </td>
            <td width="70%">
                Blue dash line indicated skill connectivity
            </td>
        </tr>
        <tr>
            <td width="30%" class="dashdotline">
                —.—.—.—.—
            </td>
            <td width="70%">
                Green long dash & dot line indicated contents
            </td>
        </tr>
    </table>
    </center>

</div>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/networkgraph.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>

<script>
    // Add the nodes option through an event call. We want to start with the parent
// item and apply separate colors to each child element, then the same color to
// grandchildren.
Highcharts.addEvent(

    Highcharts.Series,
    'afterSetOptions',
    function (e) {
        var colors = Highcharts.getOptions().colors,

            i = 0,
            nodes = {};

        if (this instanceof Highcharts.seriesTypes.networkgraph && e.options.id === 'lang-tree') {
            e.options.data.forEach(function (link) {

                if (link[0] === '<?php echo $data['subject_name']; ?>') {
                    nodes['<?php echo $data['subject_name']; ?>'] = {
                        id: '<?php echo $data['subject_name']; ?>',
                        marker: {
                            radius: 50
                        }
                    };
                    nodes[link[1]] = {
                        id: link[1],
                        marker: {
                            radius: 10,
                            symbol: 'triangle'
                        },
                        color: colors[i++]
                    };
                } else if (nodes[link[0]] && nodes[link[0]].color) {
                    nodes[link[1]] = {
                        id: link[1],
                        color: nodes[link[0]].color
                    };
                }
            });

            e.options.nodes = Object.keys(nodes).map(function (id) {
                return nodes[id];
            });
        }
    }
);

var KG_data = <?php echo $data['graph_data']; ?>

Highcharts.chart('container', {
    chart: {
        type: 'networkgraph',
        height: '100%'
    },
    title: {
        text: 'Knowledge Graph of (<?php echo $data['subject_name']; ?>)'
    },
    subtitle: {
        text: 'Mapping Tree'
    },
    plotOptions: {
        point: {
        events: {
          click: function() {
            alert("d");
            var point = this;

            if (!point.linksHidden) {
              point.linksHidden = true;

              point.linksTo.forEach(function(link) {
                link.graphic.hide();

                link.fromNode.graphic.hide();
                link.fromNode.dataLabel.hide();
              })
            } else {
              point.linksHidden = false;

              point.linksTo.forEach(function(link) {
                link.graphic.show();

                link.fromNode.graphic.show();
                link.fromNode.dataLabel.show();
              })
            }
          }
        }
      },
        networkgraph: {
            keys: ['from', 'to','color','width','dashStyle'],
            layoutAlgorithm: {
                enableSimulation: true,
                friction: -0.9
            }
        }
    },
    series: [{
        dataLabels: {
            enabled: true,
            linkFormat: ''
        },
        id: 'lang-tree',
        data:  KG_data
        //data:  [['Compter_Science_final','Food_Where_does_it_come_from?'],['Compter_Science_final','Chapter_2']]
    }]
});

</script>

@include('includes.lmsfooterJs')
@include('includes.footer')
@endsection
