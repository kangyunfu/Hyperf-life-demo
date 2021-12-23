#!/bin/bash
basepath=$(cd `dirname $0`; pwd)
cd $basepath

# 更新文档
apidoc -i app/Admin/Controller/ -o public/apidoc/admin
apidoc -i app/Api/Controller/ -o public/apidoc/api
apidoc -i app/Merchant/Controller/ -o public/apidoc/merchant
