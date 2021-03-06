# Filters added to this controller will be run for all controllers in the application.
# Likewise, all the methods added will be available for all controllers.
class ApplicationController < ActionController::Base
end

require 'kwartz/helper/rails'
ActionView::Base.register_template_handler('html', Kwartz::Helper::RailsTemplate)
#Kwartz::Helper::RailsTemplate.lang = 'rails'   # or 'eruby', 'ruby', 'erubis', 'pierubis'
#Kwartz::Helper::RailsTemplate.pdata_suffix  = '.html'
#Kwartz::Helper::RailsTemplate.plogic_suffix = '.plogic'
#Kwartz::Helper::RailsTemplate.default_properties = { :escape=>false }
#Kwartz::Helper::RailsTemplate.use_cache = true
#Kwartz::Helper::RailsTemplate.debug = true

