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
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9989330746847721, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [0.990625, 500, 1500, "01_Login_Flow"], "isController": true}, {"data": [1.0, 500, 1500, "03_Access_Modules"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-0"], "isController": false}, {"data": [1.0, 500, 1500, "02_View_Dashboard"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-1"], "isController": false}, {"data": [0.997872340425532, 500, 1500, "POST_Login"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Login_Page"], "isController": false}]}, function(index, item){
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
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 3755, 0, 0.0, 19.37363515312916, 0, 4704, 11.0, 32.0, 36.0, 57.0, 32.031596546900055, 74.54322952654229, 7.227335566161668], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["01_Login_Flow", 480, 0, 0.0, 54.46875000000001, 0, 4740, 24.0, 45.900000000000034, 56.94999999999999, 770.8299999999972, 4.003135789701933, 22.69051186450219, 1.5135554100712223], "isController": true}, {"data": ["03_Access_Modules", 470, 0, 0.0, 25.729787234042558, 0, 66, 22.0, 42.0, 51.89999999999998, 61.29000000000002, 4.0519686532808015, 12.79995411899856, 1.4425372112972334], "isController": true}, {"data": ["GET_Staff_Dashboard-0", 235, 0, 0.0, 11.221276595744685, 3, 34, 9.0, 24.400000000000006, 27.19999999999999, 32.0, 2.031870098653778, 0.7659197832815999, 0.36708590649506734], "isController": false}, {"data": ["GET_Staff_Hazards", 228, 0, 0.0, 26.833333333333332, 7, 66, 22.0, 44.099999999999994, 56.64999999999995, 62.71000000000001, 1.9805420430854763, 6.392404053921994, 0.7252961583564975], "isController": false}, {"data": ["GET_Admin_Dashboard", 235, 0, 0.0, 24.391489361702142, 6, 65, 20.0, 42.0, 45.0, 62.639999999999986, 2.0258096773359306, 6.538346193094144, 0.7062637253993431], "isController": false}, {"data": ["GET_Admin_Dashboard-1", 235, 0, 0.0, 12.42978723404255, 3, 35, 10.0, 27.0, 32.0, 34.0, 2.0258969982241073, 5.7749766563863165, 0.3521578766444249], "isController": false}, {"data": ["GET_Admin_Dashboard-0", 235, 0, 0.0, 11.676595744680851, 3, 34, 9.0, 27.0, 28.19999999999999, 33.0, 2.026036727304078, 0.7637040369859471, 0.3541607169799121], "isController": false}, {"data": ["GET_Admin_Reports-0", 232, 0, 0.0, 13.249999999999998, 3, 35, 10.0, 27.0, 30.349999999999994, 34.339999999999975, 2.062130571974579, 0.7775348873383405, 0.38664948224523354], "isController": false}, {"data": ["02_View_Dashboard", 470, 0, 0.0, 24.025531914893644, 6, 66, 20.0, 41.0, 44.44999999999999, 62.29000000000002, 4.041654842676435, 13.044565290999149, 1.4208942806284344], "isController": true}, {"data": ["GET_Staff_Dashboard", 235, 0, 0.0, 23.659574468085093, 8, 66, 20.0, 40.400000000000006, 43.19999999999999, 62.19999999999993, 2.031308075962278, 6.556126163139971, 0.7200828433342842], "isController": false}, {"data": ["GET_Admin_Reports-1", 232, 0, 0.0, 12.250000000000005, 4, 34, 9.0, 25.700000000000017, 31.349999999999994, 33.0, 2.062112242902601, 5.8782194810721204, 0.35845310472330366], "isController": false}, {"data": ["POST_Login", 470, 0, 0.0, 23.97234042553192, 3, 4704, 10.0, 29.0, 33.0, 35.0, 4.0422457685427275, 11.522760544778622, 1.0342464759357368], "isController": false}, {"data": ["GET_Admin_Reports", 232, 0, 0.0, 25.754310344827584, 7, 61, 22.0, 42.0, 45.0, 59.66999999999999, 2.0618923194511103, 6.6550376245356295, 0.7450196857391707], "isController": false}, {"data": ["GET_Staff_Dashboard-1", 235, 0, 0.0, 12.093617021276602, 4, 35, 9.0, 26.400000000000006, 32.0, 34.27999999999997, 2.031413431532723, 5.790718561175799, 0.3531167879031491], "isController": false}, {"data": ["GET_Staff_Hazards-0", 228, 0, 0.0, 13.56140350877193, 3, 35, 10.0, 28.0, 31.0, 33.71000000000001, 1.9807313068482915, 0.7467785968082427, 0.3810586596182749], "isController": false}, {"data": ["GET_Staff_Hazards-1", 228, 0, 0.0, 13.02631578947369, 3, 34, 10.0, 30.099999999999994, 32.0, 33.0, 1.9809550288454854, 5.646874063281956, 0.34434569837353163], "isController": false}, {"data": ["GET_Login_Page", 475, 0, 0.0, 14.661052631578947, 3, 119, 11.0, 28.0, 32.19999999999999, 40.24000000000001, 4.054457769621441, 11.787410708569844, 0.522644946865264], "isController": false}]}, function(index, item){
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
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 3755, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
