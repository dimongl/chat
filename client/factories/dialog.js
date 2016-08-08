angular.module('app').factory('dialogFactory', function () {
    var service = {};
    service.show = false;
    service.head = '';
    service.body = '';
    service.showDialog = function(head,body){
		console.log('show Dialog');
        service.head = head;
        service.body = body;
        service.show = true;
    }
    service.closeDialog = function(){
        service.show = false;
    }

    return service;
});

angular.module('app').directive('infoDialog', function() {
  return {
    restrict: 'E',
    scope: {
      show: '=',
    },
    replace: true, // Replace with the template below
    transclude: true, // we want to insert custom content inside the directive
    link: function(scope, element, attrs) {
      scope.dialogStyle = {};
      if (attrs.width)
        scope.dialogStyle.width = attrs.width;
      if (attrs.height)
        scope.dialogStyle.height = attrs.height;
    },
//    templateUrl: './view/dialog.html'
	template:  '<div ng-show="show">'+
					'<div class="dialog" ng-controller="dialogCtrl as dialogCtrl">'+
						'<div class="dialoghead">{{dialogCtrl.dialogFactory.head}}</div>'+
						'<div class="dialogbody">'+
							'<div>{{dialogCtrl.dialogFactory.body}}</div>'+
							'<button ng-click="dialogCtrl.dialogFactory.closeDialog()">закрыть</button>'+
						'</div>'+
					'</div>'+
					'<div class="dialogback"></div>'+
				'</div>'
  };
});