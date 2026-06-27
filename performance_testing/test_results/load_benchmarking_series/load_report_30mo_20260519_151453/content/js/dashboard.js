/*
   Licensed to the Apache Software Foundation (ASF) under one or more
   contributor license agreements.  See the NOTICE file distributed with
   this work for additional information regarding copyright ownership.
   The ASF licenses this file to You under the Apache License, Version 2.0
   (the "License"); you may not use this file except in compliance with
   the License.  You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/
var showControllersOnly = false;
var seriesFilter = "";
var filtersOnlySampleSeries = true;

/*
 * Add header in statistics table to group metrics by category
 * format
 *
 */
function summaryTableHeader(header) {
    var newRow = header.insertRow(-1);
    newRow.className = "tablesorter-no-sort";
    var cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 1;
    cell.innerHTML = "Requests";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 3;
    cell.innerHTML = "Executions";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 7;
    cell.innerHTML = "Response Times (ms)";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 1;
    cell.innerHTML = "Throughput";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 2;
    cell.innerHTML = "Network (KB/sec)";
    newRow.appendChild(cell);
}

/*
 * Populates the table identified by id parameter with the specified data and
 * format
 *
 */
function createTable(table, info, formatter, defaultSorts, seriesIndex, headerCreator) {
    var tableRef = table[0];

    // Create header and populate it with data.titles array
    var header = tableRef.createTHead();

    // Call callback is available
    if(headerCreator) {
        headerCreator(header);
    }

    var newRow = header.insertRow(-1);
    for (var index = 0; index < info.titles.length; index++) {
        var cell = document.createElement('th');
        cell.innerHTML = info.titles[index];
        newRow.appendChild(cell);
    }

    var tBody;

    // Create overall body if defined
    if(info.overall){
        tBody = document.createElement('tbody');
        tBody.className = "tablesorter-no-sort";
        tableRef.appendChild(tBody);
        var newRow = tBody.insertRow(-1);
        var data = info.overall.data;
        for(var index=0;index < data.length; index++){
            var cell = newRow.insertCell(-1);
            cell.innerHTML = formatter ? formatter(index, data[index]): data[index];
        }
    }

    // Create regular body
    tBody = document.createElement('tbody');
    tableRef.appendChild(tBody);

    var regexp;
    if(seriesFilter) {
        regexp = new RegExp(seriesFilter, 'i');
    }
    // Populate body with data.items array
    for(var index=0; index < info.items.length; index++){
        var item = info.items[index];
        if((!regexp || filtersOnlySampleSeries && !info.supportsControllersDiscrimination || regexp.test(item.data[seriesIndex]))
                &&
                (!showControllersOnly || !info.supportsControllersDiscrimination || item.isController)){
            if(item.data.length > 0) {
                var newRow = tBody.insertRow(-1);
                for(var col=0; col < item.data.length; col++){
                    var cell = newRow.insertCell(-1);
                    cell.innerHTML = formatter ? formatter(col, item.data[col]) : item.data[col];
                }
            }
        }
    }

    // Add support of columns sort
    table.tablesorter({sortList : defaultSorts});
}

$(document).ready(function() {

    // Customize table sorter default options
    $.extend( $.tablesorter.defaults, {
        theme: 'blue',
        cssInfoBlock: "tablesorter-no-sort",
        widthFixed: true,
        widgets: ['zebra']
    });

    var data = {"OkPercent": 100.0, "KoPercent": 0.0};
    var dataset = [
        {
            "label" : "FAIL",
            "data" : data.KoPercent,
            "color" : "#FF6347"
        },
        {
            "label" : "PASS",
            "data" : data.OkPercent,
            "color" : "#9ACD32"
        }];
    $.plot($("#flot-requests-summary"), dataset, {
        series : {
            pie : {
                show : true,
                radius : 1,
                label : {
                    show : true,
                    radius : 3 / 4,
                    formatter : function(label, series) {
                        return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'
                            + label
                            + '<br/>'
                            + Math.round10(series.percent, -2)
                            + '%</div>';
                    },
                    background : {
                        opacity : 0.5,
                        color : '#000'
                    }
                }
            }
        },
        legend : {
            show : true
        }
    });

    // Creates APDEX table
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9969738383443967, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [0.9926778242677824, 500, 1500, "01_Login_Flow"], "isController": true}, {"data": [0.9914163090128756, 500, 1500, "03_Access_Modules"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-0"], "isController": false}, {"data": [0.9868995633187773, 500, 1500, "GET_Staff_Hazards"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-0"], "isController": false}, {"data": [0.9956331877729258, 500, 1500, "GET_Admin_Reports-0"], "isController": false}, {"data": [1.0, 500, 1500, "02_View_Dashboard"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-1"], "isController": false}, {"data": [1.0, 500, 1500, "POST_Login"], "isController": false}, {"data": [0.9956331877729258, 500, 1500, "GET_Admin_Reports"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-1"], "isController": false}, {"data": [0.9912663755458515, 500, 1500, "GET_Staff_Hazards-0"], "isController": false}, {"data": [0.9956331877729258, 500, 1500, "GET_Staff_Hazards-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Login_Page"], "isController": false}]}, function(index, item){
        switch(index){
            case 0:
                item = item.toFixed(3);
                break;
            case 1:
            case 2:
                item = formatDuration(item);
                break;
        }
        return item;
    }, [[0, 0]], 3);

    // Create statistics table
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 3732, 0, 0.0, 24.58038585209001, 0, 8048, 5.0, 22.0, 27.0, 41.0, 31.59284843559529, 73.60001571388663, 7.129928049446363], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["01_Login_Flow", 478, 0, 0.0, 29.889121338912148, 0, 2443, 9.0, 31.100000000000023, 40.049999999999955, 890.9199999999989, 3.9932165442804273, 22.609490389756314, 1.506495503454383], "isController": true}, {"data": ["03_Access_Modules", 466, 0, 0.0, 70.96137339055798, 0, 8048, 8.0, 31.0, 37.299999999999955, 1852.8399999999133, 3.9715006477125523, 12.598360357754654, 1.4199090246173383], "isController": true}, {"data": ["GET_Staff_Dashboard-0", 234, 0, 0.0, 5.568376068376068, 2, 28, 4.0, 13.0, 22.75, 28.0, 2.0450255191218627, 0.7708702255427182, 0.3694626182007271], "isController": false}, {"data": ["GET_Staff_Hazards", 229, 0, 0.0, 95.82532751091706, 5, 7273, 8.0, 30.0, 35.0, 5971.299999999992, 2.0133461109010824, 6.4982820069280205, 0.7373093667850643], "isController": false}, {"data": ["GET_Admin_Dashboard", 232, 0, 0.0, 12.112068965517246, 6, 45, 8.0, 28.0, 33.0, 43.0, 1.995904953629622, 6.441844400067104, 0.6958379574665772], "isController": false}, {"data": ["GET_Admin_Dashboard-1", 232, 0, 0.0, 5.8965517241379315, 2, 27, 4.0, 16.0, 17.349999999999994, 25.669999999999987, 1.9963686742218893, 5.690812065445612, 0.3470250234487269], "isController": false}, {"data": ["GET_Admin_Dashboard-0", 232, 0, 0.0, 6.1077586206896575, 2, 28, 4.0, 17.0, 24.349999999999994, 28.0, 1.995956467501183, 0.7523736261668172, 0.3489025465651482], "isController": false}, {"data": ["GET_Admin_Reports-0", 229, 0, 0.0, 42.048034934497814, 2, 8046, 4.0, 22.0, 25.0, 28.0, 2.0109769484083424, 0.7581641053787047, 0.3770581778265642], "isController": false}, {"data": ["02_View_Dashboard", 466, 0, 0.0, 12.221030042918457, 6, 45, 8.0, 29.0, 32.64999999999998, 43.329999999999984, 3.96558620044081, 12.799051126064795, 1.394201260945784], "isController": true}, {"data": ["GET_Staff_Dashboard", 234, 0, 0.0, 12.329059829059833, 6, 45, 8.0, 29.0, 32.0, 44.650000000000006, 2.0449719034843175, 6.600209631468097, 0.7249265634421945], "isController": false}, {"data": ["GET_Admin_Reports-1", 229, 0, 0.0, 6.353711790393014, 3, 51, 4.0, 16.0, 20.5, 28.0, 2.011029928340593, 5.732613633619327, 0.3495735617623296], "isController": false}, {"data": ["POST_Login", 466, 0, 0.0, 6.920600858369092, 3, 29, 4.0, 20.0, 24.0, 28.0, 3.9655187085684136, 11.304076796290623, 1.0146151383251214], "isController": false}, {"data": ["GET_Admin_Reports", 229, 0, 0.0, 48.576419213973814, 6, 8048, 8.0, 31.0, 38.5, 51.699999999999875, 2.010923971267497, 6.490455726193821, 0.7266033880556385], "isController": false}, {"data": ["GET_Staff_Dashboard-1", 234, 0, 0.0, 6.636752136752139, 3, 36, 4.0, 17.0, 25.0, 27.650000000000006, 2.045114884765642, 5.8297671961169035, 0.35549848582840265], "isController": false}, {"data": ["GET_Staff_Hazards-0", 229, 0, 0.0, 62.886462882096076, 2, 7269, 4.0, 22.0, 26.5, 3857.9999999999377, 2.013700196093949, 0.7591993920428065, 0.38740130725635546], "isController": false}, {"data": ["GET_Staff_Hazards-1", 229, 0, 0.0, 32.76855895196505, 3, 6169, 4.0, 15.0, 19.5, 27.69999999999999, 2.0134523233833033, 5.739518878863147, 0.34999464215061327], "isController": false}, {"data": ["GET_Login_Page", 474, 0, 0.0, 7.506329113924055, 3, 39, 5.0, 20.0, 24.0, 30.0, 4.021720685559138, 11.6922437770448, 0.5184249321228577], "isController": false}]}, function(index, item){
        switch(index){
            // Errors pct
            case 3:
                item = item.toFixed(2) + '%';
                break;
            // Mean
            case 4:
            // Mean
            case 7:
            // Median
            case 8:
            // Percentile 1
            case 9:
            // Percentile 2
            case 10:
            // Percentile 3
            case 11:
            // Throughput
            case 12:
            // Kbytes/s
            case 13:
            // Sent Kbytes/s
                item = item.toFixed(2);
                break;
        }
        return item;
    }, [[0, 0]], 0, summaryTableHeader);

    // Create error table
    createTable($("#errorsTable"), {"supportsControllersDiscrimination": false, "titles": ["Type of error", "Number of errors", "% in errors", "% in all samples"], "items": []}, function(index, item){
        switch(index){
            case 2:
            case 3:
                item = item.toFixed(2) + '%';
                break;
        }
        return item;
    }, [[1, 1]]);

        // Create top5 errors by sampler
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 3732, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
