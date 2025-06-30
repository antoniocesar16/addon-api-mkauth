# atualizar puxando do git
# apagar
rm -rf addon-api-mkauth
rm -rf addon-api-mkauth.zip

# apagar da pasta /var/www
rm -rf /var/www/addon-api-mkauth

git clone https://github.com/antoniocesar16/addon-api-mkauth

# unzip
unzip addon-api-mkauth.zip
# mover para pasta raiz
mv addon-api-mkauth/ /var/www