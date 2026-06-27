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
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9996148661659927, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [0.9958847736625515, 500, 1500, "01_Login_Flow"], "isController": true}, {"data": [1.0, 500, 1500, "03_Access_Modules"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Dashboard-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-0"], "isController": false}, {"data": [1.0, 500, 1500, "02_View_Dashboard"], "isController": true}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports-1"], "isController": false}, {"data": [1.0, 500, 1500, "POST_Login"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Admin_Reports"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Dashboard-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-0"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Staff_Hazards-1"], "isController": false}, {"data": [1.0, 500, 1500, "GET_Login_Page"], "isController": false}]}, function(index, item){
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
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 3783, 0, 0.0, 13.032778218345245, 0, 1248, 6.0, 28.0, 30.0, 43.0, 31.87616912991456, 74.1645394997388, 7.19404936572069], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["01_Login_Flow", 486, 0, 0.0, 29.450617283950624, 0, 1248, 18.0, 45.0, 50.0, 223.2499999999958, 4.056422669226275, 22.804816936712292, 1.5211911046657207], "isController": true}, {"data": ["03_Access_Modules", 472, 0, 0.0, 18.819915254237284, 0, 74, 19.0, 34.0, 39.0, 45.26999999999998, 4.039885308340822, 12.873406540206274, 1.4508224071339924], "isController": true}, {"data": ["GET_Staff_Dashboard-0", 235, 0, 0.0, 9.765957446808514, 2, 29, 4.0, 24.400000000000006, 28.0, 28.639999999999986, 2.011228646742665, 0.7581389234791689, 0.36335673793690737], "isController": false}, {"data": ["GET_Staff_Hazards", 231, 0, 0.0, 18.3116883116883, 5, 57, 18.0, 33.80000000000001, 39.0, 45.0, 2.0274540092683613, 6.543849864178135, 0.7424758334723002], "isController": false}, {"data": ["GET_Admin_Dashboard", 237, 0, 0.0, 17.742616033755272, 6, 46, 17.0, 33.0, 42.099999999999994, 44.620000000000005, 2.0444958204294306, 6.598656426035834, 0.7127783280208072], "isController": false}, {"data": ["GET_Admin_Dashboard-1", 237, 0, 0.0, 8.000000000000004, 2, 28, 4.0, 19.0, 26.0, 28.0, 2.0446016477591336, 5.82829585526032, 0.3554092708018807], "isController": false}, {"data": ["GET_Admin_Dashboard-0", 237, 0, 0.0, 9.645569620253168, 2, 29, 4.0, 26.0, 28.0, 29.0, 2.044566370765289, 0.770688833389408, 0.35739978551463547], "isController": false}, {"data": ["GET_Admin_Reports-0", 235, 0, 0.0, 8.957446808510632, 2, 70, 4.0, 24.0, 26.19999999999999, 29.0, 2.025966860354846, 0.7638124062451506, 0.3798687863165336], "isController": false}, {"data": ["02_View_Dashboard", 472, 0, 0.0, 18.32203389830506, 6, 46, 17.0, 32.0, 41.349999999999966, 44.0, 4.033739840872382, 13.019019521335236, 1.418061588242332], "isController": true}, {"data": ["GET_Staff_Dashboard", 235, 0, 0.0, 18.906382978723403, 6, 45, 17.0, 32.0, 37.19999999999999, 44.0, 2.011159797343557, 6.491096806855915, 0.7129404359723743], "isController": false}, {"data": ["GET_Admin_Reports-1", 235, 0, 0.0, 10.714893617021271, 3, 32, 4.0, 24.0, 27.0, 29.0, 2.0262288862638926, 5.775931149173557, 0.3522155681200907], "isController": false}, {"data": ["POST_Login", 472, 0, 0.0, 9.828389830508474, 3, 76, 4.0, 25.0, 27.0, 29.0, 4.033636425787927, 11.498227272338825, 1.0320436948793328], "isController": false}, {"data": ["GET_Admin_Reports", 235, 0, 0.0, 19.799999999999997, 5, 74, 20.0, 34.0, 40.0, 48.559999999999945, 2.0258969982241073, 6.538771142605906, 0.732013563811445], "isController": false}, {"data": ["GET_Staff_Dashboard-1", 235, 0, 0.0, 9.042553191489363, 3, 41, 4.0, 23.0, 26.0, 28.0, 2.011297500855871, 5.733376372068641, 0.349620073390962], "isController": false}, {"data": ["GET_Staff_Hazards-0", 231, 0, 0.0, 9.88744588744589, 2, 41, 4.0, 24.80000000000001, 27.399999999999977, 29.0, 2.0277209645280503, 0.7645186274918584, 0.390098662121119], "isController": false}, {"data": ["GET_Staff_Hazards-1", 231, 0, 0.0, 8.259740259740267, 2, 30, 4.0, 19.80000000000001, 26.0, 28.0, 2.027560782936891, 5.779736255266391, 0.35244708922145174], "isController": false}, {"data": ["GET_Login_Page", 477, 0, 0.0, 10.773584905660364, 3, 48, 5.0, 26.0, 28.0, 30.21999999999997, 4.019617757103853, 11.6861205606187, 0.5181538515016685], "isController": false}]}, function(index, item){
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
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 3783, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
