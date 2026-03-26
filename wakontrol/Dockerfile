FROM python:3.10-slim

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE 1
ENV PYTHONUNBUFFERED 1
ENV PORT=8080

WORKDIR /app

# Install dependencies needed for some Python packages (optional but good practice)
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements and install (adjusted for root build context)
COPY wakontrol/requirements.txt /app/
RUN pip install --no-cache-dir -r requirements.txt

# Copy project
COPY wakontrol/ /app/

# Create data directory for Papuyu PaaS persistent storage mapping
RUN mkdir -p /data

# Expose port
EXPOSE $PORT

# Command to run application
CMD ["python", "main.py"]
