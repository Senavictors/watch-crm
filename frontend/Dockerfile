FROM node:20-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 4001

CMD ["npm", "run", "dev", "--", "-p", "4001", "--hostname", "0.0.0.0"]
