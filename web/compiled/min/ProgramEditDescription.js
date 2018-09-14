function ProgramEditDescription(programId,showMoreButtonText,showLessButtonText,statusCode_OK,statusCode_DESCRIPTION_TOO_LONG,statusCode_RUDE_WORD_IN_DESCRIPTION){$(function(){let description=$("#description"),editDescriptionUI=$("#edit-description-ui"),editDescriptionButton=$("#edit-description-button"),editDescription=$("#edit-description"),editDescriptionSubmitButton=$("#edit-description-submit-button"),editDescriptionError=$("#edit-description-error");editDescriptionUI.hide(),description.show(),editDescriptionButton.hover(function(){editDescriptionButton.addClass("fa-lg")},function(){editDescriptionButton.removeClass("fa-lg")}),editDescriptionButton.click(function(){description.is(":visible")?(description.hide(),editDescriptionUI.show()):(description.show(),editDescriptionUI.hide())}),editDescriptionSubmitButton.click(function(){let newDescription=editDescription.val().trim();if(newDescription===description.text().trim())return editDescriptionUI.hide(),void description.show();let url=Routing.generate("edit_program_description",{id:programId,newDescription:newDescription},!1);$.get(url,function(data){data.statusCode===statusCode_OK?location.reload():data.statusCode!==statusCode_DESCRIPTION_TOO_LONG&&data.statusCode!==statusCode_RUDE_WORD_IN_DESCRIPTION||(editDescription.addClass("danger"),editDescriptionError.show(),editDescriptionError.text(data.message))})})}),$(function(){let descriptionFulltext=$("#descriptionFulltext"),descriptionPoints=$("#descriptionPoints"),descriptionShowMoreToggle=$("#descriptionShowMoreToggle"),descriptionShowMoreText=$("#descriptionShowMoreText");descriptionFulltext.hide(),descriptionPoints.show(),descriptionShowMoreToggle.click(function(){descriptionFulltext.is(":visible")?(descriptionFulltext.fadeOut(),descriptionPoints.show(),descriptionShowMoreText.text(showMoreButtonText),descriptionShowMoreToggle.css("aria-expanded",!1)):(descriptionFulltext.fadeIn(),descriptionPoints.hide(),descriptionShowMoreText.text(showLessButtonText),descriptionShowMoreToggle.css("aria-expanded",!0))})})}