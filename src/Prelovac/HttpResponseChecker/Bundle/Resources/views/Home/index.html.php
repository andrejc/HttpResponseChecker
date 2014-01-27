<!doctype html>
<html lang="en" ng-app="HttpResponseChecker">
<head>
  <meta charset="utf-8">
  <title>HTTP Response Checker</title>
  <script src="lib/angular/angular.js"></script>
  <script src="bundles/responsechecker/js/HRCController.js"></script>
  <script src="http://code.jquery.com/jquery-1.10.1.min.js"></script>
  <script src="http://code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
  <link rel="stylesheet" type="text/css" href="bundles/responsechecker/css/stylesheet.css">
  
  <!--Tree-like structure for displaying chain redirects-->
  <script type="text/ng-template"  id="url-tree.html">
    <b>URL:</b> {{url.address}} <br/>
    <b>Response: </b>
    <span ng-if="url.state != STATE_WAITING_RESPONSE">
      <font class="hover" title={{HTTP_STATUS[url.response].explanation}} color="{{url.response_color}}">
        <span ng-if="url.response > 0">{{url.response}}</span> {{HTTP_STATUS[url.response].text}}
      </font> </br>
      <span ng-if="url.content_length >= 0"><b>Content length:</b> {{url.content_length | number}}</span>
    </span>
    <span ng-if="url.state == STATE_WAITING_RESPONSE">Still waiting <img src="bundles/responsechecker/images/icon_loading.gif" hspace="20"/></span>
    <span ng-if="url.redirect.length > 0"><b>Redirects to:</b></span>
    <ul id="redir-list">
      <li ng-repeat="url in url.redirect" ng-include="'url-tree.html'"></li>
    </ul>
  </script>
</head>
<body ng-controller="HPCController">
  <div align="center">
    <h1>HTTP Response Checker</h1><br/>
    <h3>You may request up to {{MAX_URLS}} URLs per batch</h3>
    <textarea name="urls" ng-model="input" ng-change="onInputChange()"></textarea>
    <div>URL count: {{totalUrls}}</div>
    <div ng-if="totalUrls > MAX_URLS"><img src="bundles/responsechecker/images/icon_warning.png"/><i> Warning: The total number of entered URLs exceeds the maximum allowed number. You can still submit the request, but only first {{MAX_URLS}} URLs will be processed.</i></div>
    <a class="mButton" ng-click="submitData()">Submit</a>
  </div>
  <ul id="batch-list">
    <li ng-repeat="batch in batches">
      <h3 id="batch{{batch.id}}">Batch {{batch.id+1}}
      <div><progress value="{{batch.processed}}" max="{{batch.urls.length}}"></progress> 
        <span ng-if="batch.processed < batch.urls.length">{{(batch.processed / batch.urls.length) * 100 | number:0}}%</span> 
        <span ng-if="batch.processed == batch.urls.length" style="margin-left:1em;">Complete!</span>
      </div></h3>
      <br/>
      <ul id="url-list">
        <li ng-repeat="url in batch.urls" ng-include="'url-tree.html'"></li>
      </ul>
    </li>
  </ul>

</body>
</html>
