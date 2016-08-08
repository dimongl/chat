var app = angular.module('app', ['ngSanitize']);

angular.module('app').controller('mainCtrl', function (wsService,usersFactory,dialogFactory,$scope) {
	this.usersFactory = usersFactory;  
	this.dialogFactory = dialogFactory; 

	if (wsService.getStatus() != 1){
			wsService.init('ws://127.0.0.1:8889');
	}	
	setTimeout(function(){
			if (wsService.getStatus() == 1){
				wsService.send('{"action":"getusers"}')
			}
			else{
				console.log(wsService);
				dialogFactory.showDialog('Ошибка соединения','Неудалось подключиться к серверу, проверьте настройки');
				$scope.$digest();
			}
		},100
	);
});