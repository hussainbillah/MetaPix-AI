const jwt = require('jsonwebtoken');
const User = require('../models/User');
const logger = require('../utils/logger');

const auth = async (req, res, next) => {
  try {
    // Get token from header
    const token = req.header('Authorization')?.replace('Bearer ', '');
    
    if (!token) {
      return res.status(401).json({ error: 'No token, authorization denied' });
    }

    // Verify token
    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your-secret-key');
    
    // Get user from token
    const user = await User.findById(decoded.userId);
    if (!user) {
      return res.status(401).json({ error: 'Token is not valid' });
    }

    // Check if user is active
    if (!user.isActive) {
      return res.status(401).json({ error: 'Account is deactivated' });
    }

    // Add user to request object
    req.user = {
      id: user._id,
      email: user.email,
      role: user.role
    };

    next();
  } catch (error) {
    logger.error('Auth middleware error:', error);
    
    if (error.name === 'JsonWebTokenError') {
      return res.status(401).json({ error: 'Token is not valid' });
    }
    
    if (error.name === 'TokenExpiredError') {
      return res.status(401).json({ error: 'Token has expired' });
    }
    
    res.status(500).json({ error: 'Server error in authentication' });
  }
};

// Optional auth middleware for routes that can work with or without authentication
const optionalAuth = async (req, res, next) => {
  try {
    const token = req.header('Authorization')?.replace('Bearer ', '');
    
    if (!token) {
      return next(); // Continue without user
    }

    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your-secret-key');
    const user = await User.findById(decoded.userId);
    
    if (user && user.isActive) {
      req.user = {
        id: user._id,
        email: user.email,
        role: user.role
      };
    }

    next();
  } catch (error) {
    // Continue without user if token is invalid
    next();
  }
};

// Role-based authorization middleware
const authorize = (...roles) => {
  return (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ error: 'Authentication required' });
    }

    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ error: 'Access denied. Insufficient permissions.' });
    }

    next();
  };
};

// API key authentication middleware
const apiKeyAuth = async (req, res, next) => {
  try {
    const apiKey = req.header('X-API-Key');
    
    if (!apiKey) {
      return res.status(401).json({ error: 'API key required' });
    }

    // Find user by API key
    const user = await User.findOne({
      'apiKeys.key': apiKey,
      'apiKeys.isActive': true
    });

    if (!user) {
      return res.status(401).json({ error: 'Invalid API key' });
    }

    // Check if user is active
    if (!user.isActive) {
      return res.status(401).json({ error: 'Account is deactivated' });
    }

    // Validate API key and update last used
    const validApiKey = user.validateApiKey(apiKey);
    if (!validApiKey) {
      return res.status(401).json({ error: 'Invalid API key' });
    }

    // Add user to request object
    req.user = {
      id: user._id,
      email: user.email,
      role: user.role,
      apiKey: validApiKey
    };

    // Increment API call usage
    await user.incrementUsage('apiCalls');

    next();
  } catch (error) {
    logger.error('API key auth error:', error);
    res.status(500).json({ error: 'Server error in API key authentication' });
  }
};

module.exports = { auth, optionalAuth, authorize, apiKeyAuth };