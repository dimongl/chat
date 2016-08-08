angular.module('app').controller('messagesCtrl', function (messagesFactory,dialogFactory,usersFactory,wsService,$scope,$filter) {

    this.messagesFactory = messagesFactory;
    this.dialogFactory = dialogFactory;
	this.sendMessage = function(msg){
		if (wsService.getStatus() != 1){
			dialogFactory.showDialog('Ошибка соединения','Неудалось подключиться к серверу, проверьте настройки');
		}		
		else if (msg){
			wsService.send('{"action":"sendmessage","user": "'+usersFactory.Me+'","message":"'+ $filter('encode_chars')(msg)+'"}');
			$scope.message = '';
		}
	}

});

angular.module('app').filter('encode_chars', function() {
  return function(str) {
  console.log('FILTER');
	  return str.replace(/\&/g, '&#38;')
				.replace(/\n/g, '&#10;')
				.replace(/\r/g, '&#13;')
				.replace(/\>/g, '&#62;')
				.replace(/\</g, '&#60;')
				.replace(/\"/g, '&#34;')
				.replace(/\'/g, '&#145;')
				.replace(/\\/g, '&#92;')
				.replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;')
				;
  }
});

angular.module('app').directive('schrollBottom', function () {
  return {
    scope: {
      schrollBottom: "="
    },
    link: function (scope, element) {
      scope.$watchCollection('schrollBottom', function (newValue) {
        if (newValue){
			setTimeout(function(){
				$(element).scrollTop($(element)[0].scrollHeight);
			},100);
         // $(element).scrollTop($(element)[0].scrollHeight);
        }
      });
    }
  }
})