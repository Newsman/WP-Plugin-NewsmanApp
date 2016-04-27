(function ($) {
    $(function () {
 
    	$(".newsman-subscription-form").submit(function(e){
    		e.preventDefault();
    		
    		var email = $(this).find("input[name='newsman_subscription_email']").val();
    		
    		$.post(ajaxurl, {    			 
                action:     'newsman_ajax_subscribe',
                email:    email
            }, function (response) {
            	
            	response = jQuery.parseJSON(response);

                $("#newsman_subscribtion_message").html(response.message);
                $("#newsman_subscribtion_message").addClass(response.status);

            });
    	});
 
    	//show template preview
    	$("#newsman-preview-newsletter").on("click", function(event){
    		var errors = false;
    		
    		$('input[name="newsman_newsletter_title"]').removeClass('input-error-border');
    		$('#use_email_template').removeClass('input-error-border');
    		$('#use_email_template').next('.input-error').css('display', 'none');
			$('input[name="newsman_newsletter_title"]').next('.input-error').css('display', 'none');
    		
    		if( $('input[name="newsman_newsletter_title"]').val().trim() == "" ){
    			 $('input[name="newsman_newsletter_title"]').addClass('input-error-border');
    			 $('input[name="newsman_newsletter_title"]').next('.input-error').show('fast').css('display', 'inline-block');
    			 errors = true;
        	}
    		
    		if( $('#use_email_template').val() == 0 ){
        		$('#use_email_template').addClass('input-error-border');
        		$('#use_email_template').next('.input-error').show('fast').css('display', 'inline-block');
        		errors = true;
        	}
    		
    		if( errors ){
    			event.preventDefault();
    			return;
    		}
    		
    		$('#NewsmanModal .modal-body').html("You must select a template and a minimum of 1 post!");
			var template = $("#use_email_template").val();
			var posts = getSelectedPosts();
			var subject = $("input[name='newsman_newsletter_title']").val();
    		$('#NewsmanModal .modal-title').html(subject);
			    		
    		$.post(ajaxurl, {    			 
                action:     'newsman_ajax_preview_template',
                template:    template,
                posts: posts
            }, function (response) {
            	response = jQuery.parseJSON(response);
        		$('#NewsmanModal .modal-body').html(response.html);
        		$('#NewsmanModal .modal-title').html(subject);
            });
    		$('#NewsmanModal').modal('show');
        });
        
      //send newsletter
        $(".newsman-send-newsletter").on("click", function(){
        	var errors = false;
    		
    		$('input[name="newsman_newsletter_title"]').removeClass('input-error-border');
    		$('#use_email_template').removeClass('input-error-border');
    		$('#use_email_template').next('.input-error').css('display', 'none');
			$('input[name="newsman_newsletter_title"]').next('.input-error').css('display', 'none');
    		
    		if( $('input[name="newsman_newsletter_title"]').val().trim() == "" ){
    			 $('input[name="newsman_newsletter_title"]').addClass('input-error-border');
    			 $('input[name="newsman_newsletter_title"]').next('.input-error').show('fast').css('display', 'inline-block');
    			 errors = true;
        	}
    		
    		if( $('#use_email_template').val() == 0 ){
        		$('#use_email_template').addClass('input-error-border');
        		$('#use_email_template').next('.input-error').show('fast').css('display', 'inline-block');
        		errors = true;
        	}
    		
    		if( errors ){
    			event.preventDefault();
    			return;
    		}
        	
        	if( $('input[name="newsman_newsletter_title"]').val().trim() == "" ){
        		$('.input-error').show('fast');
        	}
        	
			var template = $("#use_email_template").val();
			var subject = $("input[name='newsman_newsletter_title']").val();
			var list = $("input[name='newsman_list']").val();
			var posts = getSelectedPosts();


    		$.post(ajaxurl, {    			 
                action:     'newsman_ajax_send_newsletter',
                template:    template,
                subject: subject,
                list: list,
                posts: posts
            }, function (response) {            	
            	response = jQuery.parseJSON(response);
            	
            	if( response.status == "1" ){
            		$('#nws-message').addClass('updated');
            		$('#nws-message strong').html('Sent succesfully.');
            	}else{
            		$('#nws-message').addClass('error');
            		$('#nws-message strong').html('Newsletter was not sent. There was an error!');
            	}
            });
        });
        
        getSelectedPosts = function(){
        	var ids = "";
        	
        	$('.newsletter-posts ul.selected li').each(function(){
        		if( $(this).attr("id") !== undefined ) {
            		ids += $(this).attr("id") + ", ";        			
        		}
        	} );
        	return ids;
        }
        
        //newsletter posts select and sorting
        var newsletterPosts = function(selector){
            this.list = [];
            selector = selector;
            this.add = function(entry){
                obj.list.push(entry);
                this.pushToSelected(entry);
            }
            this.remove = function(entry){
                for (var i = 0; i < obj.list.length; i++) {
                    if( obj.list[i]['id'] == entry['id'] ){
                        obj.list.splice(i, 1);
                    }
                }
                this.pushToAvailable(entry);
            }
            this.pushToAvailable = function(entry){
                var html = '<li id="'+entry['id']+'" data-title="'+entry['title']+'">';
                html += '<span>'+entry['title']+'</span> ';
                html += '<span><a href="#">select</a></span>';
                html += '</li>';
                $(selector + ' ul.available').append(html);
                
                $(selector + ' ul.selected').find('li[id="'+entry['id']+'"]').remove();
            }
            this.pushToSelected = function(entry){
                var html = '<li title="click and drag to reorder" id="'+entry['id']+'" data-title="'+entry['title']+'">';
                html += '<span>'+entry['title']+'</span> ';
                html += '<span><a href="#">remove</a></span>';
                html += '</li>';
                $(selector + ' ul.selected').append(html);
                
                $(selector + ' ul.available').find('li[id="'+entry['id']+'"]').remove();
            }

                
            $(selector+' ul.selected').sortable({
                items: "li:not(.newsletter-posts-header)"
            });
            
            // object reference to itself
            var obj = this;
           
            $(selector+" ul.selected").on("click", "a", function(e){
                    e.preventDefault();
                    var id = $(this).closest('li').attr('id');
                    var title = $(this).closest('li').data('title');
                    
                    var entry = [];
                    entry['id'] = id;
                    entry['title'] = title;
                    obj.remove(entry);
            });
            
            $(selector+" ul.available").on("click", "a", function(e){
                    e.preventDefault();
                    var id = $(this).closest('li').attr('id');
                    var title = $(this).closest('li').data('title');
                    
                    var entry = [];
                    entry['id'] = id;
                    entry['title'] = title;
                    obj.add(entry);
            });
          
        }

        var handle = new newsletterPosts('.newsletter-posts');
        
        
        /*
         * Template editing
         */
        
        //template selection list
        $(document).on('change', '.newsman-select-list', function(){
        	var filename = $(this).val();
        	
        	$.post(ajaxurl, {    			 
                action:     'newsman_ajax_template_editor_selection',
                template:    filename
            }, function (response) {
            	response = jQuery.parseJSON(response);
            	$('textarea[name="newsman_template_edit"]').val(response.source);
            });
        });
        
        //saving template editor changes
        $(document).on('click', '#newsman-templates-editor-save', function(){
        	var filename = $('select[name="newsman_templates_list"]').val();
        	var source = $('textarea[name="newsman_template_edit"]').val();
        	
        	$.post(ajaxurl, {    			 
                action:     'newsman_ajax_template_editor_save',
                template:    filename,
                source: source,
            }, function (response) {
            	data = jQuery.parseJSON(response);
            	var error = data.response.error;
            	var message = data.response.message;
            	var container = $('.save-status');

            	if(!error){
            		container.removeClass('save-error');
            		container.html(message);
            		container.addClass('save-success', 500);
            		
            		setTimeout(function(){
        				container.removeClass('save-success', 500).html('&nbsp;');
            		}, 2000);
            	}else{
            		container.removeClass('save-success');
            		container.html(message);
            		container.addClass('save-error', 500);
            		
            		setTimeout(function() {
        				container.removeClass('save-success', 500).html('&nbsp;');
            			}, 2000);
            	}
            });
        })
        
        //templete editor preview
        $(document).on('click', '#newsman-templates-editor-preview', function(){
        	var filename = $('select[name="newsman_templates_list"]').val();
        	
        	$.post(ajaxurl, {    			 
                action:     'newsman_ajax_preview_template',
                template:    filename,
                posts: 10
            }, function (response) {
            	response = jQuery.parseJSON(response);
        		$('#NewsmanModal .modal-body').html(response.html);
            });
    		$('#NewsmanModal').modal('show');
        });
        
        //template variables
        $(document).on('click', '.newsman-template-variables dt', function(){
        	$('.newsman-template-variables dd').slideUp();
        	$(this).next('dd').slideDown();
        });
    });
}(jQuery));