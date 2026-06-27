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
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9989082969432315, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [0.9946808510638298, 500, 1500, "01_Login_Flow"], "isController": true}, {"data": [1.0, 500, 1500, "03_Access_Modules"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards"], "isController": false}, {"data": [0.9956709956709957, 500, 1500, "GET_Admin_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-1"], "isController": false}, {"data": [0.9956709956709957, 500, 1500, "GET_Admin_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-0"], "isController": false}, {"data": [0.9978213507625272, 500, 1500, "02_View_Dashboard"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-1"], "isController": false}, {"data": [1.0, 500, 1500, "POST_Login"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Login_Page"], "isController": false}]}, function(index, item){
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
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 3670, 0, 0.0, 14.202179836512249, 0, 2477, 7.0, 26.0, 31.0, 45.0, 30.946177261727083, 72.00822877404231, 6.981911743315372], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["01_Login_Flow", 470, 0, 0.0, 34.01914893617022, 0, 1406, 19.5, 41.0, 49.44999999999999, 968.6200000000036, 3.924024212064287, 22.186578676163638, 1.4798743738259235], "isController": true}, {"data": ["03_Access_Modules", 459, 0, 0.0, 18.305010893246205, 0, 208, 18.0, 36.0, 42.0, 61.19999999999993, 3.9529776514662185, 12.508536459006159, 1.4097772816819534], "isController": true}, {"data": ["GET_Staff_Dashboard-0", 228, 0, 0.0, 7.666666666666667, 2, 37, 3.0, 20.099999999999994, 29.099999999999966, 36.130000000000024, 1.9728132489984511, 0.7436581192513693, 0.35641645611788425], "isController": false}, {"data": ["GET_Staff_Hazards", 225, 0, 0.0, 18.27111111111113, 6, 208, 17.0, 33.400000000000006, 40.69999999999999, 67.44000000000005, 1.9969291667036466, 6.445340235127315, 0.7312973022596363], "isController": false}, {"data": ["GET_Admin_Dashboard", 231, 0, 0.0, 27.01298701298702, 5, 2477, 15.0, 34.0, 40.0, 52.480000000000075, 1.9891843483053184, 6.42017018666902, 0.6934949339306627], "isController": false}, {"data": ["GET_Admin_Dashboard-1", 231, 0, 0.0, 8.103896103896103, 2, 34, 4.0, 18.600000000000023, 27.0, 32.0, 1.9892871290539262, 5.670633915730869, 0.345794051730077], "isController": false}, {"data": ["GET_Admin_Dashboard-0", 231, 0, 0.0, 18.696969696969695, 2, 2473, 4.0, 21.0, 26.0, 29.0, 1.9892528676242636, 0.7498550848661776, 0.3477307258835383], "isController": false}, {"data": ["GET_Admin_Reports-0", 225, 0, 0.0, 10.528888888888893, 2, 33, 8.0, 24.400000000000006, 27.0, 32.74000000000001, 1.9551106592633145, 0.737129330027024, 0.3665832486118714], "isController": false}, {"data": ["02_View_Dashboard", 459, 0, 0.0, 21.217864923747292, 5, 2477, 8.0, 32.0, 41.0, 51.79999999999984, 3.9426892748544042, 12.725183645892388, 1.3860262024558057], "isController": true}, {"data": ["GET_Staff_Dashboard", 228, 0, 0.0, 15.34649122807017, 5, 63, 7.0, 31.0, 43.54999999999998, 55.39000000000007, 1.9725230992836629, 6.366395354621587, 0.6992440283593453], "isController": false}, {"data": ["GET_Admin_Reports-1", 225, 0, 0.0, 8.324444444444438, 2, 34, 4.0, 20.0, 27.69999999999999, 34.0, 1.9552635695291727, 5.5736468354059125, 0.3398798001720632], "isController": false}, {"data": ["POST_Login", 459, 0, 0.0, 10.923747276688447, 3, 226, 4.0, 26.0, 28.0, 37.39999999999998, 3.941538144471542, 11.235718364648095, 1.0084794861831485], "isController": false}, {"data": ["GET_Admin_Reports", 225, 0, 0.0, 19.0711111111111, 6, 63, 19.0, 37.0, 42.69999999999999, 55.700000000000045, 1.9550596944893384, 6.310175787020142, 0.7064180536729054], "isController": false}, {"data": ["GET_Staff_Dashboard-1", 228, 0, 0.0, 7.4956140350877165, 2, 36, 4.0, 17.0, 24.549999999999983, 34.71000000000001, 1.9726254953193403, 5.623138496911284, 0.34289779117855723], "isController": false}, {"data": ["GET_Staff_Hazards-0", 225, 0, 0.0, 9.27111111111111, 2, 33, 4.0, 25.0, 27.69999999999999, 32.48000000000002, 1.9970000621288906, 0.7529487647445171, 0.3841884885150308], "isController": false}, {"data": ["GET_Staff_Hazards-1", 225, 0, 0.0, 8.768888888888892, 2, 200, 4.0, 18.0, 27.0, 35.74000000000001, 1.997159595242322, 5.693075057140955, 0.34716250776673174], "isController": false}, {"data": ["GET_Login_Page", 464, 0, 0.0, 10.941810344827596, 3, 208, 4.0, 25.0, 31.0, 56.35000000000093, 3.91557877148716, 11.38366415420545, 0.5047425760120168], "isController": false}]}, function(index, item){
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
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 3670, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
