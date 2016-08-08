angular.module('app').controller('usersCtrl', function (usersFactory,dialogFactory,wsService,$scope) {

    this.usersFactory = usersFactory;    
    this.dialogFactory = dialogFactory;
	this.login = function(name){
		if(name){
			console.log('aaaa', wsService.getStatus());
			if (wsService.getStatus() != 1){
				dialogFactory.showDialog('Ошибка соединения','Неудалось подключиться к серверу, проверьте настройки');
			}	
			else if (usersFactory.addUser(name)){
				usersFactory.Me = name;
				wsService.send('{"action":"auth","name":"'+name+'"}');
			}
			else{
				dialogFactory.showDialog('Ошибка входа','Возможно такой пользователь уже онлайн');
			}
		}
		$scope.newuser = '';
	}
});
