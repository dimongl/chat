angular.module('app').factory('usersFactory', function ($filter) {
var service = {};
	service.users = [];
	service.Me;
	
	service.addUser = function(name){
		if ($filter('checkUser')(service.users, name)){
			console.log('User exist');
			return false;
		}
		else{
			console.log('addUser',name);
			var newuser = {
				name: name
			};
			service.users.push(newuser);	
			return true;
		}	
	}
	
	service.clearUsers = function(){
		service.users = [];
	}
return service;
});

angular.module('app').filter('checkUser', function() {
  return function(users, name) {
	var res = false;
    var i=0, len=users.length;
    for (; i<len; i++) {
	console.log('checking user '+name+' ? ',users[i].name);
      if (users[i].name == name) {
        res = true;
      }
    }
    return res;
  }
});