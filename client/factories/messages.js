angular.module('app').factory('messagesFactory', function (usersFactory,$filter,$sce) {
var service = {};
	service.messages = []

	service.addMessage = function(user, text){
		if (user && text){
			var newmessage = {
				user: user,
				message: $filter('decode_chars')(text)
			}
			service.messages.push(newmessage);	
			return true;
		}
		else{
			return false;
		}
	}
	
	service.clearMessages = function(){
		service.messages = [];
	}
return service;
});

angular.module('app').filter('decode_chars', function() {
  return function(str) {
//	  return str.replace(/&#13;/g, "\n").replace(/&#10;/g, "\r");
	  return str.replace(/&#13;/g, "<br/>").replace(/&#10;/g, "<br/>");
  }
});

