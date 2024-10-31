(function(){

                    tinymce.PluginManager.requireLangPack('pilotpress');

                    tinymce.create('tinymce.plugins.pilotpress', {

                        init : function(ed, url){
                        },
                        createControl : function(n, cm){
                            switch(n) {
                                case "merge_fields":
                                    var mlb = cm.createListBox('merge_fields', {
                                                         title : 'Merge Fields',
                                                         onselect : function(v) {
                                                            if(v!="") {
                                                                tinyMCE.activeEditor.execCommand('mceInsertContent', false, '[pilotpress_field name=\''+v+'\']');
                                                            }   
                                                         }
                                                    });

                                    for (var group in pilotpress_tiny_mce_plugin_default_fields_old)
                                    {
                                        mlb.add(group, '');
                                        for (var key in pilotpress_tiny_mce_plugin_default_fields_old[group])
                                        {
                                            mlb.add(key, key);
                                        }
                                    }
                                    return mlb;


                                break;
                            }
                            return null;
                        },

                        getInfo : function(){
                            return {
                                longname: 'PilotPress',
                                author: 'Ontraport Inc.',
                                authorurl: 'http://Ontraport.com',
                                infourl: 'http://Ontraport.com/',
                                version: "2.0.6"
                            };
                        }
                    });
                    tinymce.PluginManager.add('pilotpress', tinymce.plugins.pilotpress);
                })();