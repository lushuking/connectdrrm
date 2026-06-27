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
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9997106481481481, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [0.9968814968814969, 500, 1500, "01_Login_Flow"], "isController": true}, {"data": [1.0, 500, 1500, "03_Access_Modules"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-0"], "isController": false}, {"data": [1.0, 500, 1500, "02_View_Dashboard"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-1"], "isController": false}, {"data": [1.0, 500, 1500, "POST_Login"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Login_Page"], "isController": false}]}, function(index, item){
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
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 3776, 0, 0.0, 18.060646186440696, 0, 2926, 12.0, 33.0, 38.0, 57.23000000000002, 31.80941309274095, 74.00966112992495, 7.178742552566403], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["01_Login_Flow", 481, 0, 0.0, 39.00207900207901, 0, 2926, 24.0, 45.0, 53.0, 198.82000000000033, 4.016064257028112, 22.86024946981272, 1.5259843949602985], "isController": true}, {"data": ["03_Access_Modules", 473, 0, 0.0, 26.105708245243108, 0, 119, 22.0, 42.0, 46.299999999999955, 62.25999999999999, 4.095699083013673, 12.883975171447869, 1.4520709040844424], "isController": true}, {"data": ["GET_Staff_Dashboard-0", 236, 0, 0.0, 12.74152542372881, 2, 209, 9.0, 26.0, 29.0, 33.889999999999986, 2.0359746365871545, 0.7674670016822671, 0.3678274489927964], "isController": false}, {"data": ["GET_Staff_Hazards", 230, 0, 0.0, 27.334782608695654, 6, 119, 23.0, 43.0, 47.0, 64.38, 2.039025168662843, 6.581215189740157, 0.7467133186021152], "isController": false}, {"data": ["GET_Admin_Dashboard", 238, 0, 0.0, 26.62605042016806, 7, 173, 21.0, 43.0, 54.099999999999966, 64.21999999999997, 2.0579512144506222, 6.642101044972287, 0.717469319881711], "isController": false}, {"data": ["GET_Admin_Dashboard-1", 238, 0, 0.0, 13.05462184873949, 3, 35, 10.0, 30.099999999999994, 33.0, 35.0, 2.060962937305161, 5.874943510239868, 0.3582533230862487], "isController": false}, {"data": ["GET_Admin_Dashboard-0", 238, 0, 0.0, 13.235294117647067, 3, 168, 9.0, 28.0, 30.049999999999983, 50.65999999999991, 2.0584851970696856, 0.775943981417414, 0.35983286159714234], "isController": false}, {"data": ["GET_Admin_Reports-0", 231, 0, 0.0, 12.978354978354982, 3, 35, 9.0, 27.0, 29.0, 34.0, 2.0922965445405555, 0.7888392452787464, 0.3923056021013541], "isController": false}, {"data": ["02_View_Dashboard", 474, 0, 0.0, 26.339662447257364, 6, 214, 21.0, 43.0, 53.0, 65.0, 4.076612799188118, 13.157410254315275, 1.4331337939590447], "isController": true}, {"data": ["GET_Staff_Dashboard", 236, 0, 0.0, 26.02966101694915, 6, 214, 21.0, 42.0, 52.0, 76.33999999999992, 2.0357814467850184, 6.570564142211411, 0.7216686183427359], "isController": false}, {"data": ["GET_Admin_Reports-1", 231, 0, 0.0, 12.922077922077927, 3, 34, 10.0, 28.0, 32.0, 33.68000000000001, 2.091936535535753, 5.963244870340686, 0.3636374055911758], "isController": false}, {"data": ["POST_Login", 474, 0, 0.0, 13.713080168776369, 3, 36, 11.0, 28.0, 30.25, 34.0, 4.076577738789411, 11.620677169337084, 1.043030632385572], "isController": false}, {"data": ["GET_Admin_Reports", 231, 0, 0.0, 26.238095238095223, 7, 63, 22.0, 41.0, 47.599999999999795, 59.68000000000001, 2.0917471068692612, 6.751336983515041, 0.7558070600992448], "isController": false}, {"data": ["GET_Staff_Dashboard-1", 236, 0, 0.0, 12.93220338983051, 3, 75, 10.0, 28.30000000000001, 32.0, 34.629999999999995, 2.0394759583808635, 5.813701486829825, 0.35451828182792355], "isController": false}, {"data": ["GET_Staff_Hazards-0", 230, 0, 0.0, 14.078260869565216, 2, 109, 10.0, 30.0, 33.0, 47.20999999999998, 2.039224030925276, 0.7688823556140725, 0.39231165438699156], "isController": false}, {"data": ["GET_Staff_Hazards-1", 230, 0, 0.0, 12.986956521739128, 3, 36, 9.5, 30.900000000000006, 32.0, 35.0, 2.0392059509349316, 5.81292314908812, 0.35447134693986115], "isController": false}, {"data": ["GET_Login_Page", 477, 0, 0.0, 16.488469601677185, 4, 436, 12.0, 29.0, 32.0, 39.43999999999994, 4.037890138913579, 11.73922680382372, 0.5205092757193286], "isController": false}]}, function(index, item){
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
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 3776, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
