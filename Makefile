build:
	docker build -t ogd-logger:testing .

run:
	docker run -p 8080:8080 ogd-logger:testing