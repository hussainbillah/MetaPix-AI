# Ads Manager - Social Media Advertising Platform

A comprehensive web application for managing social media advertising campaigns across multiple platforms. Built with Node.js, Express, MongoDB, React, and TypeScript.

## 🚀 Features

### Core Functionality
- **Multi-Platform Support**: Facebook, Instagram, Twitter, LinkedIn, TikTok, Google Ads, YouTube, Pinterest, Snapchat
- **Campaign Management**: Create, edit, duplicate, and manage advertising campaigns
- **Ad Creation**: Design and manage ads with rich media support
- **Performance Analytics**: Real-time tracking and reporting
- **API Key Management**: Secure API key generation and management
- **User Management**: Role-based access control and user preferences

### Advanced Features
- **Real-time Analytics**: Live performance monitoring and insights
- **Creative Management**: Image and video upload with optimization
- **Targeting Tools**: Advanced audience targeting and segmentation
- **Budget Management**: Daily and lifetime budget controls
- **Automated Optimization**: AI-powered campaign optimization
- **Multi-currency Support**: USD, EUR, GBP, CAD, AUD, JPY, INR

### Technical Features
- **RESTful API**: Comprehensive API with JWT authentication
- **Real-time Updates**: WebSocket integration for live data
- **File Upload**: Secure file handling with image optimization
- **Rate Limiting**: API abuse prevention
- **Error Handling**: Comprehensive error logging and monitoring
- **Dark Mode**: Full dark/light theme support

## 🛠 Tech Stack

### Backend
- **Node.js** - Runtime environment
- **Express.js** - Web framework
- **MongoDB** - Database
- **Mongoose** - ODM
- **JWT** - Authentication
- **bcryptjs** - Password hashing
- **Joi** - Validation
- **Winston** - Logging
- **Multer** - File uploads
- **CORS** - Cross-origin resource sharing

### Frontend
- **React 18** - UI library
- **TypeScript** - Type safety
- **React Router** - Navigation
- **React Query** - Data fetching
- **React Hook Form** - Form management
- **Tailwind CSS** - Styling
- **Recharts** - Data visualization
- **Lucide React** - Icons
- **Framer Motion** - Animations

## 📋 Prerequisites

- Node.js (v16 or higher)
- MongoDB (v4.4 or higher)
- npm or yarn

## 🚀 Installation

### 1. Clone the repository
```bash
git clone <repository-url>
cd ads-manager
```

### 2. Install dependencies
```bash
# Install server dependencies
npm install

# Install client dependencies
cd client
npm install
cd ..
```

### 3. Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env
```

### 4. Database Setup
```bash
# Start MongoDB (if not running)
mongod

# The application will create the database automatically
```

### 5. Start the application
```bash
# Development mode (runs both server and client)
npm run dev

# Production mode
npm run build
npm start
```

## 🔧 Configuration

### Environment Variables

Create a `.env` file in the root directory:

```env
# Server Configuration
NODE_ENV=development
PORT=5000
CLIENT_URL=http://localhost:3000

# Database
MONGODB_URI=mongodb://localhost:27017/ads-manager

# JWT
JWT_SECRET=your-super-secret-jwt-key

# Social Media API Keys
FACEBOOK_APP_ID=your-facebook-app-id
FACEBOOK_APP_SECRET=your-facebook-app-secret
TWITTER_API_KEY=your-twitter-api-key
# ... other platform keys
```

### Platform API Keys

To use the platform integrations, you'll need to obtain API keys from:

- **Facebook**: [Facebook Developers](https://developers.facebook.com/)
- **Twitter**: [Twitter API](https://developer.twitter.com/)
- **LinkedIn**: [LinkedIn API](https://developer.linkedin.com/)
- **Google**: [Google Ads API](https://developers.google.com/google-ads/api)
- **TikTok**: [TikTok for Business](https://ads.tiktok.com/marketing_api/)
- **Pinterest**: [Pinterest API](https://developers.pinterest.com/)
- **Snapchat**: [Snapchat Ads API](https://marketingapi.snapchat.com/)

## 📖 API Documentation

### Authentication Endpoints

```http
POST /api/auth/register
POST /api/auth/login
GET /api/auth/me
PUT /api/auth/profile
POST /api/auth/logout
```

### Campaign Endpoints

```http
GET /api/campaigns
POST /api/campaigns
GET /api/campaigns/:id
PUT /api/campaigns/:id
DELETE /api/campaigns/:id
POST /api/campaigns/:id/duplicate
POST /api/campaigns/:id/status
```

### Ad Endpoints

```http
GET /api/ads
POST /api/ads
GET /api/ads/:id
PUT /api/ads/:id
DELETE /api/ads/:id
POST /api/ads/:id/duplicate
PUT /api/ads/:id/performance
```

### Analytics Endpoints

```http
GET /api/analytics/overview
GET /api/analytics/performance
GET /api/analytics/top-performers
GET /api/analytics/trends
GET /api/analytics/export
```

### API Keys Endpoints

```http
GET /api/api-keys
POST /api/api-keys
GET /api/api-keys/:id
PUT /api/api-keys/:id
DELETE /api/api-keys/:id
POST /api/api-keys/:id/regenerate
POST /api/api-keys/:id/toggle
```

## 🏗 Project Structure

```
ads-manager/
├── server/
│   ├── config/
│   │   └── database.js
│   ├── middleware/
│   │   └── auth.js
│   ├── models/
│   │   ├── User.js
│   │   ├── Campaign.js
│   │   └── Ad.js
│   ├── routes/
│   │   ├── auth.js
│   │   ├── campaigns.js
│   │   ├── ads.js
│   │   ├── analytics.js
│   │   ├── apiKeys.js
│   │   └── platforms.js
│   ├── utils/
│   │   └── logger.js
│   └── index.js
├── client/
│   ├── src/
│   │   ├── components/
│   │   ├── contexts/
│   │   ├── pages/
│   │   ├── hooks/
│   │   └── utils/
│   ├── public/
│   └── package.json
├── package.json
└── README.md
```

## 🎯 Usage

### Getting Started

1. **Register an account** or use the demo credentials:
   - Email: `demo@adsmanager.com`
   - Password: `demo123`

2. **Connect your social media accounts** in the Platforms section

3. **Create your first campaign**:
   - Go to Campaigns → Create Campaign
   - Choose your platform and objective
   - Set your budget and targeting
   - Create ads for your campaign

4. **Monitor performance** in the Analytics dashboard

### Key Features

#### Campaign Management
- Create campaigns with multiple objectives
- Set daily or lifetime budgets
- Configure advanced targeting options
- Duplicate successful campaigns
- Real-time status updates

#### Ad Creation
- Upload images and videos
- Create carousel ads
- Add call-to-action buttons
- Optimize ad copy with AI suggestions
- Preview ads before publishing

#### Analytics & Reporting
- Real-time performance metrics
- Platform-specific insights
- Custom date range filtering
- Export data in multiple formats
- Automated reporting

#### API Integration
- Generate secure API keys
- Set permissions for each key
- Monitor API usage
- Regenerate keys when needed

## 🔒 Security Features

- **JWT Authentication**: Secure token-based authentication
- **Password Hashing**: bcrypt for password security
- **Input Validation**: Comprehensive input sanitization
- **Rate Limiting**: API abuse prevention
- **CORS Protection**: Cross-origin request handling
- **Environment Variables**: Secure configuration management

## 🧪 Testing

```bash
# Run server tests
npm test

# Run client tests
cd client
npm test

# Run e2e tests
npm run test:e2e
```

## 📦 Deployment

### Docker Deployment

```bash
# Build the application
docker build -t ads-manager .

# Run with Docker Compose
docker-compose up -d
```

### Manual Deployment

1. **Build the client**:
   ```bash
   cd client
   npm run build
   ```

2. **Set production environment**:
   ```bash
   NODE_ENV=production
   ```

3. **Start the server**:
   ```bash
   npm start
   ```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Wiki](https://github.com/your-repo/wiki)
- **Issues**: [GitHub Issues](https://github.com/your-repo/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-repo/discussions)

## 🙏 Acknowledgments

- [React](https://reactjs.org/) - UI library
- [Express](https://expressjs.com/) - Web framework
- [MongoDB](https://www.mongodb.com/) - Database
- [Tailwind CSS](https://tailwindcss.com/) - Styling
- [Recharts](https://recharts.org/) - Data visualization

---

**Ads Manager** - Empowering businesses to manage their social media advertising campaigns efficiently and effectively.