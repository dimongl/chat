angular.module('app').service('wsService', function (usersFactory,messagesFactory,$rootScope) {
	var socket;

	this.getStatus = function(){
		if (socket && socket.readyState){
			return socket.readyState;
		}
		else{
			return 0;
		}
	}
	this.init = function(address){
		try{
			socket = new WebSocket(address);
			console.log(socket);
			socket.onopen = this.connectionOpen; 
			socket.onmessage = this.messageReceived; 
			socket.onclose = this.connectionClose; 
		} catch (e) {
			
		}
	};
	this.connectionClose = function() {
	    console.log("Соединение закрыто!");       
	}
	
	this.connectionOpen = function() {
	    console.log("Соединение открыто!");       
	}

	this.messageReceived = function (e) {
	    console.log("Ответ сервера: " + e.data);     
		var data = {};				
		try{
			data = JSON.parse(e.data);
			console.log(data);
		} catch(e) {
			data.datatype = 'info';
			data.text = e.data;
		} finally {
		}
		if (data.datatype && data.datatype == 'userslist' && data.users){
			console.log('ЭТО ПОЛЬЗОВАТЕЛИ ОНЛАЙН');
			usersFactory.clearUsers();
			var i=0, len=data.users.length;
			for (; i<len; i++) {
				usersFactory.addUser(data.users[i]);
			}
			$rootScope.$digest();
		}
		else if(data.datatype && data.datatype == 'messageslist' && data.messages){
			console.log('ЭТО ИСТОРИЯ СООБЩЕНИЙ');
			messagesFactory.clearMessages();
			var i=0, len=data.messages.length;
			for (; i<len; i++) {
				messagesFactory.addMessage(data.messages[i].user,data.messages[i].message);
			}
			$rootScope.$digest();
			
		}
		else if(data.datatype && data.datatype == 'message' && data.message && data.user){
			console.log('ЭТО СООБЩЕНИЕ');
			messagesFactory.addMessage(data.user,data.message);
			$rootScope.$digest();
		}
	}
    this.connectionClose =  function() {
        socket.close();       
    }

	this.send = function(message) {
        socket.send(message);
    };
});
