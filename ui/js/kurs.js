$(document).ready(function()  {
		
			// добавляем красивости числам
			nominalRenderer = function(cvalue,nominal) {
			
				if (nominal == 0) return 'no data';
				if (!cvalue) return 'no data';
				if (!nominal) return 'no data';
				
				var money_course = (parseFloat(cvalue.replace(",", "."))/nominal).toFixed(10);
	
				money_course = money_course.toString();
				
				if (money_course.indexOf(".")) {
				
					var i = money_course.length-1;
						
						while (i>0,i,i--) {
							
							if (money_course[i] == '0') {
								
								money_course = money_course.substring(0,i);
								
							} else break;
				
						}
						
				}
		
				if (money_course[(money_course.length-1)] == '.') 	money_course = money_course.slice(0, -1);
		
				return money_course.replace(".", ",");
			
			}
		
			// обработчик автоподгрузки данных при загрузке страницы
			$.get('stuff',function(data){
				
				if (data.status === 'success') {
			
					if (data.items.length) {
					
						data.items.forEach(function(element) {
						
							var divstring = '<div class="money" name="'+element.keycode+'"><div class="money_sub name">'+element.name+'</div><div class="money_sub keycode">'+element.keycode+'</div><div class="money_sub value">'+nominalRenderer(element.value,element.nominal)+'</div><div class="money_sub updtime">'+element.updtime+'</div><div class="money_sub refresh">обновить</div><div class="money_sub delete">удалить</div></div>';
					
							$("#data").append(divstring);
						
						});
					
					}
				
				} else {alert('Ошибка получения данных от сервера!');}
			
			},"json");
		
			// обработчик кнопки обновления данных
			$(document).on("click",".refresh",function() {
				
				var refr_div = $(this);
				
				refr_div.hide("slow");
				
				var money_div = refr_div.parent(".money");
				
				money_div.children(".value").text('обновляю..');
				// money_div.children(".updtime").text('получаю время..');
				
				money_div.children(".delete").hide();
				money_div.children(".updtime").hide();
				
				var code = money_div.attr("name").toString();
				
				$.get('codes',{keycode:code},function(data) {
				
					if (data.status === 'success') {
					
						money_div.children(".updtime").show("slow");
						refr_div.html("обновлено").show("slow");
						money_div.children(".delete").show("slow");
						
						money_div.children(".value").text(nominalRenderer(data.value[0],data.nominal[0]));
						money_div.children(".updtime").text(data.updtime);
						money_div.children(".updtime").show("slow");
						
						// if (data.nominal[0] == 1) {
						// money_div.children(".value").text(data.value[0]);
						// } else money_div.children(".value").text(data.value[0] +' с номиналом ' + data.nominal[0]);
					
					} else {
					
						refr_div.html("ошибка").show("slow");
						money_div.children(".value").hide("slow");
						money_div.children(".delete").show("slow");
						money_div.children(".updtime").hide("slow");
					
					}
					
				},"json").fail(function() { money_div.children(".updtime").hide("slow");refr_div.html("ошибка").show("slow");money_div.children(".value").hide("slow");money_div.children(".delete").show("slow"); });
				
			});
			
			// обработчик удаления данных
			$(document).on("click",".delete",function() {
		
				var money_div = $(this).parent(".money");
				var code = money_div.attr("name");
				var fullname = money_div.children(".name").text();
			
				var result = confirm('Будут удалены данные о курсе '+fullname+' ('+code+')');
				if (result) {
				
					$.get('delete',{name:fullname,keycode:code},function(data) {
						
						if (data.status === 'success') {
					
							money_div.remove();
						
						}
					
					},"json");
		
				}
			
			});

			// обработчик  добавления данных
			$("#add").click(function() {
			
				var name = prompt("Название валюты: ", "Доллары");
				
				if (name) {
				
					var keycode = prompt("Сокращение (CharCode -  3 символа A-Z): ", "USD");
					
					if (keycode.length != 3) {
					
						alert('CharCode должен состоять из трёх символов');
					
						keycode = prompt("Сокращение (CharCode -  3 символа): ", "USD");
					
					}
					
					if (name != null && keycode != null ) {
					
						// var name_encoded = encodeURIComponent(name);
						// var keycode_encoded = encodeURIComponent(keycode);
					
						$.get('add',{name:name,keycode:keycode},function(data) {
							
							if (data.status === 'success') {
						
								if (!data.updtime) data.updtime = 'нет данных';
						
								var divstring = '<div class="money" name="'+keycode+'"><div class="money_sub name">'+name+'</div><div class="money_sub keycode">'+keycode+'</div><div class="money_sub value">'+nominalRenderer(data.value,data.nominal)+'</div><div class="money_sub updtime">'+data.updtime+'</div><div class="money_sub refresh">обновить</div><div class="money_sub delete">удалить</div></div>';
						
								$("#data").append(divstring);
							
							}
							
							if (data.status === 'duplicate') {
							
								alert('Валюта с такими данными уже присутствует в списке!');
							
							}
						
						},"json");
					
					}
				
				}
			
			});
			
});