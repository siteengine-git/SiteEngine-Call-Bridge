'use strict';
const fs=require('node:fs'),path=require('node:path');
module.exports=function extract(api='https://fixture.invalid/siteengine-call/v1/'){
 const source=fs.readFileSync(path.join(__dirname,'../../src/Frontend/DniSnippet.php'),'utf8');
 const body=source.split('<script id="secb-dni-script">')[1]?.split('</script>')[0];
 if(!body)throw Error('DNI script boundary changed');
 const values=[JSON.stringify(api),JSON.stringify(['+12025550190']),'90'];let index=0;
 const script=body.replace(/<\?php[\s\S]*?\?>/g,()=>values[index++]);
 if(index!==3||script.includes('<?php'))throw Error('DNI PHP slots changed');
 return script;
};
